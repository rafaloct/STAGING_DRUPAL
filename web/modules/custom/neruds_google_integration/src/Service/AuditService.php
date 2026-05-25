<?php

declare(strict_types=1);

namespace Drupal\neruds_google_integration\Service;

use Drupal\Core\Cache\CacheBackendInterface;
use Drupal\Core\Config\ConfigFactoryInterface;
use Google\Cloud\BigQuery\BigQueryClient;
use Psr\Log\LoggerInterface;

/**
 * Reads public, auditable citations from BigQuery.
 */
final class AuditService {

  private const ALLOWED_SIGILO = ['publico', 'anonimizado'];
  private const MAX_BYTES_BILLED = 52428800;

  public function __construct(
    private readonly CacheBackendInterface $cache,
    private readonly ConfigFactoryInterface $configFactory,
    private readonly LoggerInterface $logger,
  ) {}

  /**
   * Returns public audit citations with safe filters and facets.
   */
  public function citations(array $filters = []): array {
    $filters = $this->normalizeFilters($filters);
    $cid = 'neruds_google_integration:audit_citations:' . hash('sha256', json_encode($filters));

    return $this->remember($cid, function () use ($filters): array {
      $client = $this->bigQueryClient();
      if (!$client) {
        return [
          'items' => [],
          'total' => 0,
          'facets' => [],
          'filters' => $filters,
          'notice' => 'BigQuery credentials are not configured.',
        ];
      }

      try {
        [$where, $params] = $this->whereClause($filters);
        $allItems = [];
        $total = 0;
        $facetBuckets = [
          'projetos' => [],
          'sigilo' => [],
          'fontes' => [],
        ];
        $fetchLimit = $filters['limit'] + $filters['offset'];

        foreach ($this->auditTableNames() as $table) {
          try {
            $items = $this->runRows($client, <<<SQL
SELECT
  id_citacao,
  projeto_origem,
  dataset_fonte,
  tabela_fonte,
  id_registro_original,
  FORMAT_TIMESTAMP('%Y-%m-%dT%H:%M:%SZ', data_referencia) AS data_referencia,
  conteudo_citacao,
  nivel_sigilo,
  hash_auditoria,
  uri_referencia,
  artigo_vinculado,
  FORMAT_TIMESTAMP('%Y-%m-%dT%H:%M:%SZ', data_insercao) AS data_insercao
FROM `$table`
WHERE $where
ORDER BY data_referencia DESC, data_insercao DESC
LIMIT @limit OFFSET 0
SQL, $params + [
              'limit' => $fetchLimit,
            ]);

            $allItems = array_merge($allItems, $items);
            $total += $this->singleInt($client, "SELECT COUNT(1) AS total FROM `$table` WHERE $where", $params, 'total');
            $facetBuckets['projetos'] = $this->mergeFacetRows($facetBuckets['projetos'], $this->facet($client, $table, $where, $params, 'projeto_origem'));
            $facetBuckets['sigilo'] = $this->mergeFacetRows($facetBuckets['sigilo'], $this->facet($client, $table, $where, $params, 'nivel_sigilo'));
            $facetBuckets['fontes'] = $this->mergeFacetRows($facetBuckets['fontes'], $this->facet($client, $table, $where, $params, 'dataset_fonte'));
          }
          catch (\Throwable $exception) {
            $this->logger->warning('BigQuery audit citation table query failed for @table: @message', [
              '@table' => $table,
              '@message' => $exception->getMessage(),
            ]);
          }
        }

        usort($allItems, static fn (array $a, array $b): int => strcmp(
          (string) (($b['data_referencia'] ?? '') . ($b['data_insercao'] ?? '')),
          (string) (($a['data_referencia'] ?? '') . ($a['data_insercao'] ?? '')),
        ));
        $items = array_slice($allItems, $filters['offset'], $filters['limit']);

        return [
          'items' => array_map(fn (array $row): array => $this->normalizeCitation($row), $items),
          'total' => $total,
          'limit' => $filters['limit'],
          'offset' => $filters['offset'],
          'filters' => $filters,
          'facets' => array_map(fn (array $rows): array => $this->finalizeFacetRows($rows), $facetBuckets),
          'source' => [
            'project' => $this->projectId(),
            'tables' => $this->auditTableNames(),
          ],
          'updated' => gmdate('c'),
        ];
      }
      catch (\Throwable $exception) {
        $this->logger->warning('BigQuery audit citation query failed: @message', ['@message' => $exception->getMessage()]);
        return [
          'items' => [],
          'total' => 0,
          'facets' => [],
          'filters' => $filters,
          'notice' => 'BigQuery audit query failed. Check IAM roles, dataset location, and query limits.',
        ];
      }
    });
  }

  /**
   * Returns schema metadata for the configured audit table.
   */
  public function schema(): array {
    $cid = 'neruds_google_integration:audit_schema:' . hash('sha256', $this->tableName());
    return $this->remember($cid, function (): array {
      $client = $this->bigQueryClient();
      if (!$client) {
        return ['columns' => [], 'notice' => 'BigQuery credentials are not configured.'];
      }

      try {
        $rows = $this->runRows($client, sprintf(
          'SELECT column_name, data_type, is_nullable FROM `%s.%s.INFORMATION_SCHEMA.COLUMNS` WHERE table_name = @table ORDER BY ordinal_position',
          $this->projectId(),
          $this->datasetId(),
        ), ['table' => $this->tableId()]);
        return [
          'columns' => $rows,
          'source' => [
            'project' => $this->projectId(),
            'dataset' => $this->datasetId(),
            'table' => $this->tableId(),
          ],
          'updated' => gmdate('c'),
        ];
      }
      catch (\Throwable $exception) {
        $this->logger->warning('BigQuery audit schema query failed: @message', ['@message' => $exception->getMessage()]);
        return ['columns' => [], 'notice' => 'BigQuery schema query failed.'];
      }
    });
  }

  /**
   * Returns public dataset summaries derived from auditable citation records.
   */
  public function datasets(array $filters = []): array {
    $filters = $this->normalizeFilters($filters);
    $cid = 'neruds_google_integration:audit_datasets:' . hash('sha256', json_encode($filters));

    return $this->remember($cid, function () use ($filters): array {
      $client = $this->bigQueryClient();
      if (!$client) {
        return [
          'items' => [],
          'total' => 0,
          'facets' => [],
          'filters' => $filters,
          'notice' => 'BigQuery credentials are not configured.',
        ];
      }

      try {
        [$where, $params] = $this->whereClause($filters);
        $datasetMap = [];
        $facetBuckets = [
          'projetos' => [],
          'fontes' => [],
          'tabelas' => [],
          'sigilo' => [],
        ];

        foreach ($this->auditTableNames() as $table) {
          try {
            $items = $this->runRows($client, <<<SQL
SELECT
  projeto_origem,
  dataset_fonte,
  tabela_fonte,
  MIN(nivel_sigilo) AS nivel_sigilo,
  COUNT(1) AS total_citacoes,
  COUNT(DISTINCT id_registro_original) AS total_registros,
  FORMAT_TIMESTAMP('%Y-%m-%dT%H:%M:%SZ', MAX(data_referencia)) AS data_mais_recente,
  FORMAT_TIMESTAMP('%Y-%m-%dT%H:%M:%SZ', MAX(data_insercao)) AS ultima_ingestao,
  MIN(uri_referencia) AS uri_referencia,
  MIN(artigo_vinculado) AS artigo_vinculado
FROM `$table`
WHERE $where
GROUP BY projeto_origem, dataset_fonte, tabela_fonte
ORDER BY total_citacoes DESC, projeto_origem ASC, dataset_fonte ASC, tabela_fonte ASC
LIMIT 200 OFFSET 0
SQL, $params);

            foreach ($items as $row) {
              $datasetMap = $this->mergeDatasetRow($datasetMap, $this->normalizeDataset($row));
            }
            $facetBuckets['projetos'] = $this->mergeFacetRows($facetBuckets['projetos'], $this->facet($client, $table, $where, $params, 'projeto_origem'));
            $facetBuckets['fontes'] = $this->mergeFacetRows($facetBuckets['fontes'], $this->facet($client, $table, $where, $params, 'dataset_fonte'));
            $facetBuckets['tabelas'] = $this->mergeFacetRows($facetBuckets['tabelas'], $this->facet($client, $table, $where, $params, 'tabela_fonte'));
            $facetBuckets['sigilo'] = $this->mergeFacetRows($facetBuckets['sigilo'], $this->facet($client, $table, $where, $params, 'nivel_sigilo'));
          }
          catch (\Throwable $exception) {
            $this->logger->warning('BigQuery audit dataset table query failed for @table: @message', [
              '@table' => $table,
              '@message' => $exception->getMessage(),
            ]);
          }
        }

        foreach ($this->inventoryDatasets($client, $filters) as $row) {
          $datasetMap = $this->mergeDatasetRow($datasetMap, $this->normalizeInventoryDataset($row), TRUE);
        }

        $items = array_values($datasetMap);
        usort($items, static fn (array $a, array $b): int => ((int) ($b['total_citacoes'] ?? 0) <=> (int) ($a['total_citacoes'] ?? 0))
          ?: strcmp((string) ($a['nome_dataset'] ?? ''), (string) ($b['nome_dataset'] ?? '')));

        $total = count($items);
        $items = array_slice($items, $filters['offset'], $filters['limit']);

        return [
          'items' => $items,
          'total' => $total,
          'limit' => $filters['limit'],
          'offset' => $filters['offset'],
          'filters' => $filters,
          'facets' => [
            'projetos' => $this->facetFromItems($items, 'projeto_origem', $facetBuckets['projetos']),
            'fontes' => $this->facetFromItems($items, 'dataset_fonte', $facetBuckets['fontes']),
            'tabelas' => $this->facetFromItems($items, 'tabela_fonte', $facetBuckets['tabelas']),
            'sigilo' => $this->facetFromItems($items, 'nivel_sigilo', $facetBuckets['sigilo']),
          ],
          'source' => [
            'project' => $this->projectId(),
            'audit_tables' => $this->auditTableNames(),
            'inventory_table' => $this->inventoryTableName(),
          ],
          'updated' => gmdate('c'),
        ];
      }
      catch (\Throwable $exception) {
        $this->logger->warning('BigQuery audit dataset query failed: @message', ['@message' => $exception->getMessage()]);
        return [
          'items' => [],
          'total' => 0,
          'facets' => [],
          'filters' => $filters,
          'notice' => 'BigQuery dataset query failed. Check IAM roles, dataset location, and query limits.',
        ];
      }
    });
  }

  private function normalizeFilters(array $filters): array {
    return [
      'project' => $this->cleanText((string) ($filters['project'] ?? '')),
      'sigilo' => in_array((string) ($filters['sigilo'] ?? ''), self::ALLOWED_SIGILO, TRUE) ? (string) $filters['sigilo'] : '',
      'q' => mb_substr($this->cleanText((string) ($filters['q'] ?? '')), 0, 120),
      'limit' => min(50, max(1, (int) ($filters['limit'] ?? 20))),
      'offset' => min(500, max(0, (int) ($filters['offset'] ?? 0))),
    ];
  }

  private function whereClause(array $filters): array {
    $where = ["nivel_sigilo IN ('publico', 'anonimizado')"];
    $params = [];

    if ($filters['project'] !== '') {
      $where[] = 'LOWER(projeto_origem) = @project';
      $params['project'] = mb_strtolower($filters['project']);
    }
    if ($filters['sigilo'] !== '') {
      $where[] = 'nivel_sigilo = @sigilo';
      $params['sigilo'] = $filters['sigilo'];
    }
    if ($filters['q'] !== '') {
      $where[] = '(LOWER(id_citacao) LIKE @q OR LOWER(hash_auditoria) LIKE @q OR LOWER(conteudo_citacao) LIKE @q OR LOWER(id_registro_original) LIKE @q OR LOWER(uri_referencia) LIKE @q OR LOWER(projeto_origem) LIKE @q OR LOWER(dataset_fonte) LIKE @q OR LOWER(tabela_fonte) LIKE @q)';
      $params['q'] = '%' . mb_strtolower($filters['q']) . '%';
    }

    return [implode(' AND ', $where), $params];
  }

  private function normalizeDataset(array $row): array {
    $project = (string) ($row['projeto_origem'] ?? '');
    $dataset = (string) ($row['dataset_fonte'] ?? '');
    $table = (string) ($row['tabela_fonte'] ?? '');
    $citations = (int) ($row['total_citacoes'] ?? 0);
    $records = (int) ($row['total_registros'] ?? 0);
    $name = trim($project . ' - ' . $this->humanizeIdentifier($table), ' -');

    return [
      'id' => $this->slug($project . '-' . $dataset . '-' . $table),
      'nome_dataset' => $name !== '' ? $name : $this->humanizeIdentifier($dataset ?: $table),
      'titulo' => $name !== '' ? $name : $this->humanizeIdentifier($dataset ?: $table),
      'descricao' => sprintf(
        'Dataset auditavel com %d registros e %d citacoes publicas ou anonimizadas para pesquisa territorial.',
        $records,
        $citations,
      ),
      'projeto_origem' => $project,
      'camada' => $dataset,
      'dataset_fonte' => $dataset,
      'tabela_fonte' => $table,
      'nivel_sigilo' => (string) ($row['nivel_sigilo'] ?? ''),
      'total_citacoes' => $citations,
      'total_registros' => $records,
      'data_mais_recente' => (string) ($row['data_mais_recente'] ?? ''),
      'ultima_ingestao' => (string) ($row['ultima_ingestao'] ?? ''),
      'uri_referencia' => (string) ($row['uri_referencia'] ?? ''),
      'artigo_vinculado' => $row['artigo_vinculado'] ?? NULL,
      'audit_url' => '/neruds/api/audit/citations?project=' . rawurlencode($project),
    ];
  }

  private function normalizeInventoryDataset(array $row): array {
    $location = (string) ($row['localizacao_bigquery'] ?? '');
    [$dataset, $table] = $this->datasetAndTableFromLocation($location);
    $project = (string) ($row['projeto_vinculado'] ?? '');
    $name = (string) ($row['nome_dataset'] ?? '');

    return [
      'id' => (string) ($row['id'] ?? $this->slug($project . '-' . $location)),
      'nome_dataset' => $name !== '' ? $name : $this->humanizeIdentifier($table ?: $dataset),
      'titulo' => $name !== '' ? $name : $this->humanizeIdentifier($table ?: $dataset),
      'descricao' => (string) ($row['descricao'] ?? ''),
      'projeto_origem' => $project,
      'camada' => (string) ($row['camada'] ?? ($dataset ?: 'inventario')),
      'dataset_fonte' => $dataset ?: (string) ($row['fonte'] ?? ''),
      'tabela_fonte' => $table,
      'nivel_sigilo' => 'anonimizado',
      'total_citacoes' => 0,
      'total_registros' => 0,
      'data_mais_recente' => (string) ($row['data_coleta'] ?? ''),
      'ultima_ingestao' => (string) ($row['data_ingestao'] ?? ''),
      'uri_referencia' => (string) ($row['localizacao_gcs'] ?? $location),
      'artigo_vinculado' => NULL,
      'audit_url' => '/neruds/api/audit/citations?project=' . rawurlencode($project),
      'status' => (string) ($row['status'] ?? ''),
      'licenca' => (string) ($row['licenca'] ?? ''),
      'metodologia' => (string) ($row['metodologia'] ?? ''),
      'responsavel_coleta' => (string) ($row['responsavel_coleta'] ?? ''),
    ];
  }

  private function mergeDatasetRow(array $map, array $dataset, bool $preferMetadata = FALSE): array {
    $key = $this->datasetKey($dataset);
    if (!isset($map[$key])) {
      $map[$key] = $dataset;
      return $map;
    }

    $existing = $map[$key];
    $existing['total_citacoes'] = (int) ($existing['total_citacoes'] ?? 0) + (int) ($dataset['total_citacoes'] ?? 0);
    $existing['total_registros'] = max((int) ($existing['total_registros'] ?? 0), (int) ($dataset['total_registros'] ?? 0));
    foreach (['data_mais_recente', 'ultima_ingestao'] as $dateKey) {
      if (($dataset[$dateKey] ?? '') > ($existing[$dateKey] ?? '')) {
        $existing[$dateKey] = $dataset[$dateKey];
      }
    }

    if ($preferMetadata) {
      foreach (['id', 'nome_dataset', 'titulo', 'descricao', 'camada', 'uri_referencia', 'status', 'licenca', 'metodologia', 'responsavel_coleta'] as $field) {
        if (!empty($dataset[$field])) {
          $existing[$field] = $dataset[$field];
        }
      }
    }

    $map[$key] = $existing;
    return $map;
  }

  private function datasetKey(array $dataset): string {
    return mb_strtolower(implode('|', [
      (string) ($dataset['projeto_origem'] ?? ''),
      (string) ($dataset['dataset_fonte'] ?? ''),
      (string) ($dataset['tabela_fonte'] ?? ''),
    ]));
  }

  private function datasetAndTableFromLocation(string $location): array {
    $parts = explode('.', $location);
    $count = count($parts);
    if ($count >= 4) {
      return [$parts[$count - 3] . '.' . $parts[$count - 2], $parts[$count - 1]];
    }
    if ($count >= 2) {
      return [$parts[$count - 2], $parts[$count - 1]];
    }
    return ['', ''];
  }

  private function normalizeCitation(array $row): array {
    $content = json_decode((string) ($row['conteudo_citacao'] ?? ''), TRUE);
    if (!is_array($content)) {
      $content = ['texto' => (string) ($row['conteudo_citacao'] ?? '')];
    }

    return [
      'id' => (string) ($row['id_citacao'] ?? ''),
      'projeto_origem' => (string) ($row['projeto_origem'] ?? ''),
      'fonte' => [
        'dataset' => (string) ($row['dataset_fonte'] ?? ''),
        'tabela' => (string) ($row['tabela_fonte'] ?? ''),
        'registro_original' => (string) ($row['id_registro_original'] ?? ''),
      ],
      'data_referencia' => (string) ($row['data_referencia'] ?? ''),
      'conteudo' => $content,
      'nivel_sigilo' => (string) ($row['nivel_sigilo'] ?? ''),
      'hash_auditoria' => (string) ($row['hash_auditoria'] ?? ''),
      'uri_referencia' => (string) ($row['uri_referencia'] ?? ''),
      'artigo_vinculado' => $row['artigo_vinculado'] ?? NULL,
      'data_insercao' => (string) ($row['data_insercao'] ?? ''),
    ];
  }

  private function facet(BigQueryClient $client, string $table, string $where, array $params, string $field): array {
    $rows = $this->runRows($client, <<<SQL
SELECT $field AS label, COUNT(1) AS count
FROM `$table`
WHERE $where
GROUP BY $field
ORDER BY count DESC, label ASC
LIMIT 20
SQL, $params);

    return array_map(static fn (array $row): array => [
      'label' => (string) ($row['label'] ?? ''),
      'value' => mb_strtolower((string) ($row['label'] ?? '')),
      'count' => (int) ($row['count'] ?? 0),
    ], $rows);
  }

  private function inventoryDatasets(BigQueryClient $client, array $filters): array {
    $table = $this->inventoryTableName();
    $where = ["LOWER(status) = 'ativo'"];
    $params = [];

    if ($filters['project'] !== '') {
      $where[] = '(LOWER(projeto_vinculado) = @project OR LOWER(fonte) = @project)';
      $params['project'] = mb_strtolower($filters['project']);
    }
    if ($filters['q'] !== '') {
      $where[] = '(LOWER(id) LIKE @q OR LOWER(nome_dataset) LIKE @q OR LOWER(descricao) LIKE @q OR LOWER(fonte) LIKE @q OR LOWER(projeto_vinculado) LIKE @q OR LOWER(localizacao_bigquery) LIKE @q)';
      $params['q'] = '%' . mb_strtolower($filters['q']) . '%';
    }

    try {
      return $this->runRows($client, sprintf(
        'SELECT id, nome_dataset, descricao, fonte, CAST(data_coleta AS STRING) AS data_coleta, responsavel_coleta, projeto_vinculado, camada, localizacao_gcs, localizacao_bigquery, CAST(data_ingestao AS STRING) AS data_ingestao, versao, status, observacoes, abstracao, metodologia, licenca, TO_JSON_STRING(tags) AS tags FROM `%s` WHERE %s ORDER BY nome_dataset ASC LIMIT 200',
        $table,
        implode(' AND ', $where),
      ), $params);
    }
    catch (\Throwable $exception) {
      $this->logger->warning('BigQuery inventory dataset query failed for @table: @message', [
        '@table' => $table,
        '@message' => $exception->getMessage(),
      ]);
      return [];
    }
  }

  private function mergeFacetRows(array $existing, array $incoming): array {
    foreach ($incoming as $row) {
      $label = (string) ($row['label'] ?? '');
      if ($label === '') {
        continue;
      }
      $key = mb_strtolower($label);
      if (!isset($existing[$key])) {
        $existing[$key] = [
          'label' => $label,
          'value' => $key,
          'count' => 0,
        ];
      }
      $existing[$key]['count'] += (int) ($row['count'] ?? 0);
    }
    return $existing;
  }

  private function finalizeFacetRows(array $rows): array {
    $rows = array_values($rows);
    usort($rows, static fn (array $a, array $b): int => ((int) ($b['count'] ?? 0) <=> (int) ($a['count'] ?? 0))
      ?: strcmp((string) ($a['label'] ?? ''), (string) ($b['label'] ?? '')));
    return array_slice($rows, 0, 20);
  }

  private function facetFromItems(array $items, string $field, array $seed = []): array {
    $rows = $seed;
    foreach ($items as $item) {
      $label = (string) ($item[$field] ?? '');
      if ($label === '') {
        continue;
      }
      $key = mb_strtolower($label);
      if (!isset($rows[$key])) {
        $rows[$key] = [
          'label' => $label,
          'value' => $key,
          'count' => 0,
        ];
      }
      $rows[$key]['count'] = max((int) $rows[$key]['count'], (int) ($item['total_citacoes'] ?? 1));
    }
    return $this->finalizeFacetRows($rows);
  }

  private function singleInt(BigQueryClient $client, string $sql, array $params, string $key): int {
    $rows = $this->runRows($client, $sql, $params);
    return (int) ($rows[0][$key] ?? 0);
  }

  private function runRows(BigQueryClient $client, string $sql, array $params = []): array {
    $config = $client->query($sql)
      ->useLegacySql(FALSE)
      ->maximumBytesBilled(self::MAX_BYTES_BILLED);
    if ($params !== []) {
      $config->parameters($params);
    }

    $rows = [];
    foreach ($client->runQuery($config) as $row) {
      $normalized = [];
      foreach ($row as $key => $value) {
        $normalized[$key] = is_scalar($value) || $value === NULL ? $value : (string) $value;
      }
      $rows[] = $normalized;
    }
    return $rows;
  }

  private function bigQueryClient(): ?BigQueryClient {
    $path = $this->credentialsPath();
    if ($path === '' || !is_readable($path)) {
      return NULL;
    }
    return new BigQueryClient([
      'projectId' => $this->projectId(),
      'keyFilePath' => $path,
    ]);
  }

  private function credentialsPath(): string {
    foreach (['NERUDS_BIGQUERY_APPLICATION_CREDENTIALS', 'NERUDS_GOOGLE_APPLICATION_CREDENTIALS', 'GOOGLE_APPLICATION_CREDENTIALS'] as $envName) {
      $value = getenv($envName);
      if (is_string($value) && trim($value) !== '') {
        return trim($value);
      }
    }
    return is_readable('/opt/drupal/private/chatbot-key-v2.json') ? '/opt/drupal/private/chatbot-key-v2.json' : '';
  }

  private function projectId(): string {
    return $this->setting('bigquery_project_id', 'NERUDS_BIGQUERY_PROJECT_ID', 'neruds-staging-2026');
  }

  private function datasetId(): string {
    return $this->setting('bigquery_audit_dataset', 'NERUDS_BIGQUERY_AUDIT_DATASET', 'auditoria_citacoes_us');
  }

  private function tableId(): string {
    return $this->setting('bigquery_audit_table', 'NERUDS_BIGQUERY_AUDIT_TABLE', 'citacoes_auditaveis');
  }

  private function tableName(): string {
    return $this->projectId() . '.' . $this->datasetId() . '.' . $this->tableId();
  }

  private function auditTableNames(): array {
    $tables = [$this->tableName()];
    $secondaryDataset = $this->setting('bigquery_secondary_audit_dataset', 'NERUDS_BIGQUERY_SECONDARY_AUDIT_DATASET', 'auditoria_citacoes');
    $secondaryTable = $this->setting('bigquery_secondary_audit_table', 'NERUDS_BIGQUERY_SECONDARY_AUDIT_TABLE', $this->tableId());
    if ($secondaryDataset !== '' && $secondaryTable !== '') {
      $tables[] = $this->projectId() . '.' . $secondaryDataset . '.' . $secondaryTable;
    }
    return array_values(array_unique($tables));
  }

  private function inventoryTableName(): string {
    $dataset = $this->setting('bigquery_inventory_dataset', 'NERUDS_BIGQUERY_INVENTORY_DATASET', 'cur_inventario_neruds');
    $table = $this->setting('bigquery_inventory_table', 'NERUDS_BIGQUERY_INVENTORY_TABLE', 'inventario_datasets');
    return $this->projectId() . '.' . $dataset . '.' . $table;
  }

  private function setting(string $configName, string $envName, string $default): string {
    $value = getenv($envName);
    if (is_string($value) && trim($value) !== '') {
      return trim($value);
    }
    return (string) ($this->configFactory->get('neruds_google_integration.settings')->get($configName) ?: $default);
  }

  private function remember(string $cid, callable $callback): array {
    if ($cached = $this->cache->get($cid)) {
      return is_array($cached->data) ? $cached->data : [];
    }
    $data = $callback();
    $ttl = (int) ($this->configFactory->get('neruds_google_integration.settings')->get('cache_ttl') ?: 300);
    $this->cache->set($cid, $data, time() + $ttl, ['neruds_google_integration']);
    return $data;
  }

  private function cleanText(string $value): string {
    return trim(preg_replace('/[^\p{L}\p{N}\s_.:@\/-]+/u', '', $value) ?: '');
  }

  private function humanizeIdentifier(string $value): string {
    $value = trim(str_replace(['_', '-'], ' ', $value));
    return $value === '' ? '' : mb_convert_case($value, MB_CASE_TITLE, 'UTF-8');
  }

  private function slug(string $value): string {
    $value = mb_strtolower($value);
    $ascii = @iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $value);
    if (is_string($ascii) && trim($ascii) !== '') {
      $value = $ascii;
    }
    $value = preg_replace('/[^a-z0-9]+/', '-', $value) ?: '';
    return trim($value, '-');
  }

}
