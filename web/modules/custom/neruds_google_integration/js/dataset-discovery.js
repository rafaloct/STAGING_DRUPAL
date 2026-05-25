(function (Drupal, once, drupalSettings) {
  const text = (value) => (value === null || value === undefined ? "" : String(value));

  const buildUrl = (endpoint, params) => {
    const url = new URL(endpoint, window.location.origin);
    Object.entries(params).forEach(([key, value]) => {
      if (value !== "" && value !== null && value !== undefined) {
        url.searchParams.set(key, value);
      }
    });
    return url;
  };

  const createElement = (tag, className, content) => {
    const element = document.createElement(tag);
    if (className) {
      element.className = className;
    }
    if (content !== undefined) {
      element.textContent = content;
    }
    return element;
  };

  const formatDate = (value) => {
    if (!value) {
      return "Sem data";
    }
    const date = new Date(value);
    if (Number.isNaN(date.getTime())) {
      return text(value);
    }
    return date.toLocaleDateString("pt-BR", { day: "2-digit", month: "2-digit", year: "numeric" });
  };

  const setStatus = (state, message) => {
    state.status.textContent = message;
  };

  const renderSummary = (state, data) => {
    state.summary.innerHTML = "";
    [
      ["Datasets", data.total || 0],
      ["Citacoes", (data.items || []).reduce((total, item) => total + Number(item.total_citacoes || 0), 0)],
      ["Registros", (data.items || []).reduce((total, item) => total + Number(item.total_registros || 0), 0)],
    ].forEach(([label, value]) => {
      const card = createElement("div", "neruds-dataset-discovery__stat");
      card.append(createElement("span", "", label));
      card.append(createElement("strong", "", value));
      state.summary.append(card);
    });
  };

  const renderProjectOptions = (state, data) => {
    if (state.project.dataset.loaded === "true") {
      return;
    }
    const projects = data.facets && data.facets.projetos ? data.facets.projetos : [];
    projects.forEach((project) => {
      const option = document.createElement("option");
      option.value = text(project.label);
      option.textContent = `${text(project.label)} (${Number(project.count || 0)})`;
      state.project.append(option);
    });
    state.project.dataset.loaded = "true";
  };

  const renderCard = (item, auditEndpoint) => {
    const card = createElement("article", "neruds-dataset-card");
    const header = createElement("header", "neruds-dataset-card__header");
    const badge = createElement("span", "neruds-dataset-card__badge", text(item.projeto_origem || "NERUDS"));
    const title = createElement("h2", "", text(item.nome_dataset || item.titulo || "Dataset NERUDS"));
    header.append(badge, title);

    const description = createElement("p", "neruds-dataset-card__description", text(item.descricao || "Dataset auditavel."));

    const meta = createElement("dl", "neruds-dataset-card__meta");
    [
      ["Camada", item.camada || item.dataset_fonte],
      ["Tabela", item.tabela_fonte],
      ["Sigilo", item.nivel_sigilo],
      ["Atualizacao", formatDate(item.ultima_ingestao || item.data_mais_recente)],
    ].forEach(([label, value]) => {
      const wrapper = createElement("div", "");
      wrapper.append(createElement("dt", "", label));
      wrapper.append(createElement("dd", "", text(value || "Nao informado")));
      meta.append(wrapper);
    });

    const counts = createElement("div", "neruds-dataset-card__counts");
    counts.append(createElement("span", "", `${Number(item.total_registros || 0)} registros`));
    counts.append(createElement("span", "", `${Number(item.total_citacoes || 0)} citacoes`));

    const actions = createElement("div", "neruds-dataset-card__actions");
    const auditLink = createElement("a", "", "Ver citacoes");
    const auditUrl = buildUrl(auditEndpoint, { project: item.projeto_origem || "" });
    auditLink.href = auditUrl.toString();
    const requestLink = createElement("a", "", "Solicitar acesso");
    requestLink.href = `mailto:neruds@uft.edu.br?subject=${encodeURIComponent("Solicitacao de acesso: " + text(item.nome_dataset || ""))}`;
    actions.append(auditLink, requestLink);

    card.append(header, description, meta, counts, actions);
    return card;
  };

  const renderSkeletonCard = () => {
    const skeleton = createElement("div", "neruds-dataset-card neruds-skeleton");
    skeleton.innerHTML = `
      <div class="neruds-skeleton-heading neruds-skeleton"></div>
      <div class="neruds-skeleton-snippet neruds-skeleton"></div>
      <div class="neruds-skeleton-text neruds-skeleton"></div>
      <div class="neruds-skeleton-text short neruds-skeleton"></div>
      <div class="neruds-skeleton-actions">
        <div class="neruds-skeleton" style="flex: 1;"></div>
        <div class="neruds-skeleton" style="flex: 1;"></div>
      </div>
    `;
    return skeleton;
  };

  const renderSkeletonGrid = (count = 6) => {
    const container = createElement("div", "neruds-dataset-discovery__grid");
    for (let i = 0; i < count; i++) {
      container.append(renderSkeletonCard());
    }
    return container;
  };

  const renderResults = (state, data) => {
    state.results.innerHTML = "";
    const items = data.items || [];
    if (!items.length) {
      const empty = createElement("p", "neruds-dataset-discovery__empty", "Nenhum dataset encontrado para os filtros atuais.");
      state.results.append(empty);
      return;
    }
    items.forEach((item) => state.results.append(renderCard(item, state.auditEndpoint)));
  };

  const loadDatasets = async (state) => {
    const params = {
      q: state.query.value.trim(),
      project: state.project.value,
      limit: state.pagination.limit.toString(),
      offset: state.pagination.offset.toString(),
    };
    setStatus(state, "Carregando datasets...");
    state.results.replaceChildren(renderSkeletonGrid(state.pagination.limit));
    try {
      const response = await window.fetchWithRetry(buildUrl(state.datasetsEndpoint, params), {
        headers: { Accept: "application/json" },
      });
      if (!response.ok) {
        throw new Error(`HTTP ${response.status}`);
      }
      const data = await response.json();
      state.pagination.total = data.total || 0;
      renderProjectOptions(state, data);
      renderSummary(state, data);
      renderResults(state, data);
      updatePagination(state);
      setStatus(state, `${Number(data.total || 0)} datasets encontrados.`);
    }
    catch (error) {
      state.results.innerHTML = "";
      renderSummary(state, { total: 0, items: [] });
      setStatus(state, "Nao foi possivel carregar os datasets agora.");
    }
  };

  const updatePagination = (state) => {
    const totalPages = Math.ceil(state.pagination.total / state.pagination.limit);
    const currentPage = Math.floor(state.pagination.offset / state.pagination.limit) + 1;
    const start = state.pagination.offset + 1;
    const end = Math.min(state.pagination.offset + state.pagination.limit, state.pagination.total);

    const paginationHTML = `
      <div class="neruds-pagination" role="navigation" aria-label="Paginação de datasets">
        <p class="neruds-pagination__info">
          Mostrando <strong>${start}-${end}</strong> de <strong>${state.pagination.total}</strong>
        </p>

        <div class="neruds-pagination__controls">
          <button class="neruds-pagination__btn" id="pagination-first"
                  ${state.pagination.offset === 0 ? 'disabled' : ''}>
            Primeira
          </button>

          <button class="neruds-pagination__btn" id="pagination-prev"
                  ${state.pagination.offset === 0 ? 'disabled' : ''}>
            Anterior
          </button>

          <span class="neruds-pagination__page">
            Página <strong>${currentPage}</strong> de <strong>${totalPages}</strong>
          </span>

          <button class="neruds-pagination__btn" id="pagination-next"
                  ${state.pagination.offset + state.pagination.limit >= state.pagination.total ? 'disabled' : ''}>
            Próxima
          </button>

          <button class="neruds-pagination__btn" id="pagination-last"
                  ${state.pagination.offset + state.pagination.limit >= state.pagination.total ? 'disabled' : ''}>
            Última
          </button>
        </div>

        <label for="pagination-limit" class="neruds-pagination__limit-label">
          Itens por página:
          <select id="pagination-limit">
            <option value="10" ${state.pagination.limit === 10 ? 'selected' : ''}>10</option>
            <option value="20" ${state.pagination.limit === 20 ? 'selected' : ''}>20</option>
            <option value="50" ${state.pagination.limit === 50 ? 'selected' : ''}>50</option>
          </select>
        </label>
      </div>
    `;

    const paginationContainer = state.pagination.container;
    if (paginationContainer) {
      paginationContainer.innerHTML = paginationHTML;

      document.getElementById('pagination-first')?.addEventListener('click', () => {
        state.pagination.offset = 0;
        loadDatasets(state);
        window.location.hash = `offset=0&limit=${state.pagination.limit}`;
      });

      document.getElementById('pagination-prev')?.addEventListener('click', () => {
        state.pagination.offset = Math.max(0, state.pagination.offset - state.pagination.limit);
        loadDatasets(state);
        window.location.hash = `offset=${state.pagination.offset}&limit=${state.pagination.limit}`;
      });

      document.getElementById('pagination-next')?.addEventListener('click', () => {
        const newOffset = state.pagination.offset + state.pagination.limit;
        if (newOffset < state.pagination.total) {
          state.pagination.offset = newOffset;
          loadDatasets(state);
          window.location.hash = `offset=${state.pagination.offset}&limit=${state.pagination.limit}`;
        }
      });

      document.getElementById('pagination-last')?.addEventListener('click', () => {
        const totalPages = Math.ceil(state.pagination.total / state.pagination.limit);
        state.pagination.offset = (totalPages - 1) * state.pagination.limit;
        loadDatasets(state);
        window.location.hash = `offset=${state.pagination.offset}&limit=${state.pagination.limit}`;
      });

      document.getElementById('pagination-limit')?.addEventListener('change', (e) => {
        state.pagination.limit = parseInt(e.target.value);
        state.pagination.offset = 0;
        loadDatasets(state);
        window.location.hash = `offset=0&limit=${state.pagination.limit}`;
      });
    }
  };

  const restorePaginationFromHash = (state) => {
    const hash = window.location.hash.substring(1);
    if (hash) {
      const params = new URLSearchParams(hash);
      state.pagination.offset = parseInt(params.get('offset')) || 0;
      state.pagination.limit = parseInt(params.get('limit')) || 20;
    }
  };

  Drupal.behaviors.nerudsDatasetDiscovery = {
    attach(context) {
      once("neruds-dataset-discovery", "[data-neruds-dataset-discovery]", context).forEach((root) => {
        const settings = drupalSettings.nerudsDatasetDiscovery || {};
        const state = {
          root,
          datasetsEndpoint: settings.datasetsEndpoint || "/neruds/api/datasets",
          auditEndpoint: settings.auditEndpoint || "/neruds/api/audit/citations",
          form: root.querySelector("[data-dataset-filters]"),
          query: root.querySelector("#neruds-dataset-query"),
          project: root.querySelector("#neruds-dataset-project"),
          status: root.querySelector("[data-dataset-status]"),
          summary: root.querySelector("[data-dataset-summary]"),
          results: root.querySelector("[data-dataset-results]"),
          pagination: {
            container: root.querySelector("[data-dataset-pagination]"),
            offset: 0,
            limit: 20,
            total: 0,
          },
        };

        state.form.addEventListener("submit", (event) => {
          event.preventDefault();
          loadDatasets(state);
        });
        state.project.addEventListener("change", () => loadDatasets(state));
        restorePaginationFromHash(state);
        loadDatasets(state);
      });
    },
  };
})(Drupal, once, drupalSettings);
