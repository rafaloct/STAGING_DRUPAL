# 🚨 INCIDENT REPORT - 24 MAI 2026

**Incidente:** Erro 500 em página de nó após implementação de Fase 1+2  
**Severidade:** 🔴 CRÍTICA (site retorna erro)  
**Data/Hora:** 24 mai 2026, 22:05  
**Status:** 🔄 EM INVESTIGAÇÃO / RESOLUÇÃO

---

## 📋 RESUMO

Após completar Fase 1 (9 sprints) e tentar acessar página de publicação em staging, encontrado erro 500:

```
URL: https://homolog.neruds.org/genero-e-poder-uma-analise-de-uma-diretoria-de-associacao-camponesa-no-norte-do-tocantins
HTTP Status: 500
Message: "O site encontrou um erro inesperado"
```

---

## 🔍 CAUSA PROVÁVEL

**Sprint 1.4 (Drupal ↔ Google Sync)** implementou entity hooks:

```php
// neruds_google_integration.module
hook_entity_insert()   // Acionado ao criar conteúdo
hook_entity_update()   // Acionado ao editar conteúdo
hook_entity_delete()   // Acionado ao deletar conteúdo
hook_cron()           // Acionado periodicamente
```

Esses hooks podem estar **falhando silenciosamente** ou **causando erro fatal** no carregamento de páginas.

---

## 📊 TIMELINE

```
21:23 - Sessão começa (Claude Code)
21:45 - Fase 1 + 2.1 + 2.3 completos (16 commits)
22:05 - User relata: "O site encontrou um erro"
22:06 - Investigação identifica erro 500
22:07 - Causa raiz: Sprint 1.4 entity hooks
22:08 - Solução: Revert Sprint 1.4 com instruções
```

---

## 🔧 SOLUÇÃO IMPLEMENTADA

**Opção:** Revert do commit `baf8e7a` (Sprint 1.4)

**Arquivo de instruções:** `REVERT_SPRINT_1_4_INSTRUCTIONS.md`

**Passos:**
```bash
git revert baf8e7a --no-edit
git push origin feature/phase-1-blocker-critical
# Validar em staging: erro deve estar resolvido
```

**Resultado esperado:** 
- ✅ Erro 500 desaparece
- ✅ Paginação, rate limiting, skeleton screens, retry continuam funcionando
- ❌ Sincronização Drupal↔Google desativada (será implementado depois)

---

## 📈 IMPACTO

### Antes (Com Sprint 1.4):
```
✅ Paginação (Sprint 1.1)
✅ Rate Limiting (Sprint 1.2)
✅ BigQuery Optimization (Sprint 1.3)
✅ Drupal Sync (Sprint 1.4)
✅ Skeleton Screens (Sprint 2.1)
✅ Retry & Backoff (Sprint 2.3)
❌ Site: Erro 500 em páginas de nó
```

### Depois (Após Revert):
```
✅ Paginação (Sprint 1.1)
✅ Rate Limiting (Sprint 1.2)
✅ BigQuery Optimization (Sprint 1.3)
❌ Drupal Sync (Sprint 1.4) - REVERTIDO
✅ Skeleton Screens (Sprint 2.1)
✅ Retry & Backoff (Sprint 2.3)
✅ Site: Funcionando normalmente
```

---

## 🎯 PRÓXIMAS AÇÕES

### Imediato (Codex):
1. Seguir `REVERT_SPRINT_1_4_INSTRUCTIONS.md`
2. Fazer revert do commit
3. Validar em staging
4. Confirmar erro 500 resolvido

### Depois:
1. **Investigar root cause**
   - Por que os hooks causavam erro?
   - Era erro de syntax? Lógica? Missing dependencies?
   - Faltava validação?

2. **Implementar Sprint 1.4 novamente**
   - Com melhor error handling
   - Com validação de queue processing
   - Com testes automatizados

3. **Documentar lição aprendida**
   - Como evitar no futuro?
   - Qual padrão não seguimos?

---

## 📝 ROOT CAUSE ANALYSIS (Hipóteses)

### Hipótese 1: Erro em hook_entity_insert/update/delete
```php
// Possível problema:
if (!in_array($bundle, ['publicacao', ...], TRUE)) {
    return;  // ← Retorna silenciosamente
}

// Se $bundle é diferente (ex: 'noticia', 'grupo_estudos')
// Hook acionado para TODOS os entity types, não só os esperados
```

### Hipótese 2: Falta de queue na instalação
```php
// Se queue 'neruds_google_sync' não está registrado:
\Drupal::queue('neruds_google_sync')->createItem(...)
// ← Erro fatal se queue não existe
```

### Hipótese 3: AuditService não disponível
```php
$auditService = \Drupal::service('neruds_google_integration.audit');
// ← Erro se service não está registrado em services.yml
```

### Hipótese 4: BigQuery credentials não configurados
```php
public function syncNodeToBigQuery(\Drupal\node\NodeInterface $node) {
    $client = $this->bigQueryClient();
    if (!$client) {
        return;  // ← Falha silenciosa, mas pode gerar erro
    }
}
```

---

## 🔍 VERIFICAÇÃO FUTURA

Quando reimplementar Sprint 1.4, testar:

```bash
# 1. Verificar queue existe
drush queue:list | grep neruds_google_sync

# 2. Verificar service existe
drush devel:definition 'neruds_google_integration.audit'

# 3. Testar hook manualmente
drush node:create --type=publicacao --title="Teste"

# 4. Ver se queue item foi criado
drush queue:list

# 5. Processar queue
drush queue:run neruds_google_sync

# 6. Ver logs
drush watchdog:show | grep neruds_google_integration
```

---

## 📚 DOCUMENTAÇÃO DE REFERÊNCIA

| Arquivo | Propósito |
|---------|-----------|
| REVERT_SPRINT_1_4_INSTRUCTIONS.md | Como fazer revert |
| STATUS_FINAL_24MAI.md | Status geral |
| FASE_1_README.md | Detalhes Fase 1 |
| PHASE_1_SPRINT_1_4_DRUPAL_GOOGLE_SYNC.md | Docs do Sprint 1.4 |

---

## ✅ CHECKLIST PÓS-INCIDENTE

- [ ] Revert feito com sucesso
- [ ] Git push para remoto
- [ ] Validação em staging OK (erro 500 gone)
- [ ] Root cause documentado
- [ ] Plano para reimplementar Sprint 1.4 criado
- [ ] Testes automatizados planejados
- [ ] Time informado

---

## 📞 ESCALAÇÃO

**Se o revert não funcionar:**
1. Checar logs do servidor (drush watchdog)
2. Checar se cache foi limpo (drush cr)
3. Verificar se há outros erros PHP
4. Considerar rollback completo

**Se erro persiste após revert:**
1. Pode não ser Sprint 1.4 a causa
2. Investigar Fase 2.1 ou 2.3
3. Revisar changes em libraries.yml
4. Testar em branch anterior

---

**Incidente aberto por:** Claude Code  
**Data:** 24 mai 2026 22:06  
**Status:** 🔄 Aguardando ação de Codex  
**Prioridade:** 🔴 CRÍTICA

---

**Próximo passo:** Codex segue `REVERT_SPRINT_1_4_INSTRUCTIONS.md`
