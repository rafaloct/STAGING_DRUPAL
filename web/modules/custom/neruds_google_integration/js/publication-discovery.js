(function (Drupal, drupalSettings, once) {
  function text(value) {
    return value ? String(value) : '';
  }

  function renderResult(item) {
    const article = document.createElement('article');
    article.className = 'neruds-publication-discovery__card';

    const heading = document.createElement('h3');
    const link = document.createElement('a');
    link.href = text(item.link);
    link.textContent = text(item.title);
    heading.append(link);

    const snippet = document.createElement('p');
    snippet.textContent = text(item.snippet);

    const actions = document.createElement('div');
    actions.className = 'neruds-publication-discovery__actions';
    const open = document.createElement('a');
    open.href = text(item.link);
    open.textContent = 'Abrir';
    const cite = document.createElement('button');
    cite.type = 'button';
    cite.dataset.citeTitle = text(item.title);
    cite.dataset.citeUrl = text(item.link);
    cite.textContent = 'Citar';
    actions.append(open, cite);

    article.append(heading, snippet, actions);
    return article;
  }

  function formatNumber(value) {
    if (value === null || value === undefined || value === '') return '0';
    return Number(value).toLocaleString('pt-BR');
  }

  function renderSummary(container, data) {
    if (!container) return;
    const metrics = data.metrics || data;
    const provider = data.provider || 'Drupal';
    const googleTotal = data.search_total;
    const matched = metrics.matched_total ?? metrics.total ?? 0;
    const filtered = metrics.filtered_total ?? matched;
    const query = data.query || metrics.query || '';

    const summary = document.createElement('div');
    summary.className = 'neruds-publication-discovery__summary-grid';

    const cards = [
      ['Resultados no escopo Google', googleTotal === null || googleTotal === undefined ? 'n/d' : formatNumber(googleTotal)],
      ['Publicações relacionadas no Drupal', formatNumber(filtered)],
      ['Base editorial de publicações', formatNumber(metrics.total || 0)],
      ['Provedor', provider],
    ];

    cards.forEach(([label, value]) => {
      const card = document.createElement('section');
      card.className = 'neruds-publication-discovery__metric';
      const strong = document.createElement('strong');
      strong.textContent = value;
      const span = document.createElement('span');
      span.textContent = label;
      card.append(strong, span);
      summary.append(card);
    });

    const caption = document.createElement('p');
    caption.className = 'neruds-publication-discovery__summary-caption';
    caption.textContent = query
      ? `Consulta: "${query}". Escopo: ${data.scope || 'publicações NERUDS'}.`
      : 'Digite um termo para cruzar busca publica e metadados editoriais.';

    container.replaceChildren(summary, caption);
  }

  function renderFacets(container, data, currentQuery) {
    if (!container) return;
    const metrics = data.metrics || data;
    const facets = metrics.facets || {};
    const labels = {
      territories: 'Territórios',
      ods: 'ODS',
      types: 'Tipos',
      years: 'Anos',
    };

    const heading = document.createElement('h3');
    heading.textContent = 'Filtros e contagens';
    const fragments = [heading];

    Object.keys(labels).forEach((key) => {
      const values = facets[key] || [];
      if (!values.length) return;
      const group = document.createElement('section');
      group.className = 'neruds-publication-discovery__facet-group';
      const title = document.createElement('h4');
      title.textContent = labels[key];
      const list = document.createElement('div');
      list.className = 'neruds-publication-discovery__facet-list';

      values.slice(0, 10).forEach((facet) => {
        const link = document.createElement('a');
        const params = new URLSearchParams();
        if (currentQuery) params.set('q', currentQuery);
        const filterName = key === 'territories' ? 'territory' : key === 'types' ? 'type' : key === 'years' ? 'year' : 'ods';
        params.set(filterName, facet.value);
        link.href = `/publicacoes-discovery?${params.toString()}`;
        link.textContent = `${facet.label} (${facet.count})`;
        list.append(link);
      });

      group.append(title, list);
      fragments.push(group);
    });

    if (fragments.length === 1) {
      const empty = document.createElement('p');
      empty.textContent = 'Sem facetas para esta consulta.';
      fragments.push(empty);
    }

    container.replaceChildren(...fragments);
  }

  function renderSkeletonCard() {
    const skeleton = document.createElement('div');
    skeleton.className = 'neruds-skeleton-card neruds-skeleton';
    skeleton.innerHTML = `
      <div class="neruds-skeleton-heading neruds-skeleton"></div>
      <div class="neruds-skeleton-snippet neruds-skeleton"></div>
      <div class="neruds-skeleton-snippet neruds-skeleton"></div>
      <div class="neruds-skeleton-actions">
        <div class="neruds-skeleton"></div>
        <div class="neruds-skeleton"></div>
      </div>
    `;
    return skeleton;
  }

  function renderSkeletonGrid(count = 5) {
    const container = document.createElement('div');
    container.className = 'neruds-skeleton-grid';
    for (let i = 0; i < count; i++) {
      container.append(renderSkeletonCard());
    }
    return container;
  }

  function renderResults(container, data) {
    const items = data.items || [];
    if (!items.length) {
      const message = document.createElement('p');
      message.textContent = data.notice || data.error || 'Nenhum resultado encontrado.';
      container.replaceChildren(message);
      return;
    }
    container.replaceChildren(...items.map(renderResult));
  }

  Drupal.behaviors.nerudsPublicationDiscovery = {
    attach(context) {
      once('neruds-publication-discovery', '[data-neruds-publication-discovery]', context).forEach((element) => {
        const form = element.querySelector('[data-publication-search-form]');
        const results = element.querySelector('[data-publication-results]');
        const summary = element.querySelector('[data-publication-summary]');
        const facets = element.querySelector('[data-publication-facets]');
        const endpoint = drupalSettings.nerudsPublicationDiscovery?.searchEndpoint || '/neruds/api/google-search';
        const metricsEndpoint = drupalSettings.nerudsPublicationDiscovery?.metricsEndpoint || '/neruds/api/publication-metrics';
        const cseCx = drupalSettings.nerudsPublicationDiscovery?.cseCx;
        const csePanel = element.querySelector('[data-google-cse]');
        const params = new URLSearchParams(window.location.search);
        const initialQuery = params.get('q') || '';

        if (initialQuery) {
          form.querySelector('[name="q"]').value = initialQuery;
        }

        if (cseCx && csePanel && !document.querySelector(`script[data-neruds-cse="${cseCx}"]`)) {
          csePanel.hidden = false;
          const script = document.createElement('script');
          script.async = true;
          script.src = `https://cse.google.com/cse.js?cx=${encodeURIComponent(cseCx)}`;
          script.dataset.nerudsCse = cseCx;
          document.head.appendChild(script);
        }

        const runSearch = (query) => {
          const requestParams = new URLSearchParams(window.location.search);
          requestParams.set('q', query);
          ['territory', 'ods', 'type', 'year'].forEach((key) => {
            if (!requestParams.get(key)) requestParams.delete(key);
          });
          const url = `${endpoint}?${requestParams.toString()}`;
          results.replaceChildren(renderSkeletonGrid(5));
          window.fetchWithRetry(url, { headers: { Accept: 'application/json' } })
            .then((response) => response.json())
            .then((data) => {
              if (typeof window.gtag === 'function') {
                window.gtag('event', 'neruds_publication_search', {
                  search_term: query,
                  provider: data.provider || 'none',
                  result_count: (data.items || []).length,
                  drupal_result_count: data.metrics?.filtered_total || 0,
                  google_result_count: data.search_total || 0,
                });
              }
              renderSummary(summary, data);
              renderFacets(facets, data, query);
              renderResults(results, data);
            })
            .catch((error) => {
              const errorMsg = document.createElement('p');
              errorMsg.textContent = 'Erro ao buscar resultados. Tente novamente.';
              results.replaceChildren(errorMsg);
              console.error('Publication search failed:', error);
            });
        };

        form.addEventListener('submit', (event) => {
          event.preventDefault();
          const query = text(new FormData(form).get('q')).trim();
          runSearch(query);
        });

        window.fetchWithRetry(`${metricsEndpoint}${window.location.search || ''}`, {
          headers: { Accept: 'application/json' },
        })
          .then((response) => response.json())
          .then((data) => {
            renderSummary(summary, data);
            renderFacets(facets, data, initialQuery);
          })
          .catch((error) => {
            console.error('Metrics fetch failed:', error);
          });

        if (initialQuery) {
          runSearch(initialQuery);
        }

        results.addEventListener('click', (event) => {
          const button = event.target.closest('[data-cite-title]');
          if (!button) return;
          const citation = `${button.dataset.citeTitle}. NERUDS. Disponivel em: ${button.dataset.citeUrl}`;
          navigator.clipboard?.writeText(citation);
          button.textContent = 'Citacao copiada';
        });
      });
    },
  };
})(Drupal, drupalSettings, once);
