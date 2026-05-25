# FASE 2 - Sprint 2.2: Testar e Corrigir Responsividade Mobile

**Status:** 🟡 PRONTO PARA EXECUTAR  
**Lacuna:** L7 - Responsividade não testada  
**Impacto:** 60% do tráfego é mobile; experiência quebrada  
**Tempo:** 24 horas (4 dias)  
**Prioridade:** P0 (CRITÉRIO DE CONCLUSÃO FASE 2)

---

## 📌 Problema

```
Documentação original:
  "Mobile testing: NOT TESTED YET"
  
Realidade:
  - 60% do tráfego é mobile
  - Nenhuma validação responsiva documentada
  - Risco: Usuários em 320px com experiência quebrada
```

---

## ✅ Plano de Testes

### Viewports a Testar

```
1. **320px** (iPhone SE, Galaxy A10)
   - Breakpoint mínimo
   - Portrait only
   
2. **768px** (iPad Mini, tablets)
   - Landscape + portrait
   - Intermediate layout
   
3. **1440px** (Desktop, laptops)
   - Baseline desktop
   - Full layout
```

### Componentes a Testar

#### 1. Publication Discovery (/publicacoes-discovery)
- **Viewport 320px**
  - [ ] Search box: acessível, focusável
  - [ ] Label legível sem truncar
  - [ ] Filtros: dropdown ou collapse?
  - [ ] Skeleton cards: 1 coluna
  - [ ] Cards: título lê completo?
  - [ ] Actions buttons: clicáveis (> 44px height)
  - [ ] Pagination: botões visíveis
  - [ ] Scroll: fluido, sem jumps

- **Viewport 768px**
  - [ ] Cards: 2 colunas
  - [ ] Filtros: lado-a-lado ou em linha
  - [ ] Buttons: 44x44px min
  - [ ] Skeleton: 2 colunas

- **Viewport 1440px**
  - [ ] Cards: 3 colunas
  - [ ] Layout desktop padrão
  - [ ] Spacing adequado

#### 2. Dataset Discovery (/datasets-discovery)
- **Viewport 320px**
  - [ ] Dados sem overflow horizontal
  - [ ] Filtros stackados verticalmente
  - [ ] Project dropdown: acessível
  - [ ] Skeleton grid: 1 coluna
  - [ ] Cards: legível
  - [ ] Pagination: números visíveis
  - [ ] Header: não quebra

- **Viewport 768px**
  - [ ] Cards: responsivas
  - [ ] Filtros: 2 colunas
  - [ ] Pagination: controles clicáveis

- **Viewport 1440px**
  - [ ] Grids: 3 colunas
  - [ ] Espacing: consistente

#### 3. Territory Map (/territorios/{territory})
- **Viewport 320px**
  - [ ] Mapa: renderiza
  - [ ] Pan/zoom: funciona com touch
  - [ ] Sidebar: recolhe ou scroll?
  - [ ] Legend: legível
  - [ ] Controls: acessíveis

- **Viewport 768px**
  - [ ] Layout: mapa + sidebar
  - [ ] Touch events: zoom pinch ok

- **Viewport 1440px**
  - [ ] Layout desktop padrão
  - [ ] Scroll suave

#### 4. Live Dashboard (/dashboard)
- **Viewport 320px**
  - [ ] Cards: 1 coluna
  - [ ] Numbers: legível
  - [ ] Skeleton: 1 coluna
  - [ ] Sem overflow

- **Viewport 768px**
  - [ ] Cards: 2 colunas
  - [ ] Layout compacto

- **Viewport 1440px**
  - [ ] Cards: 4 colunas
  - [ ] Spacing: adequado

---

## 🔧 Testes Específicos

### Input Accessibility
```javascript
// VERIFICAR em cada viewport:
// Mínimo height: 44px para touch targets
.neruds-dataset-discovery input,
.neruds-dataset-discovery select,
.neruds-dataset-discovery button {
  min-height: 44px;  // ✅ MOBILE ACCESSIBLE
}
```

### Touch Events
```javascript
// Publication Discovery: citar button
// VERIFICAR: click + touch funcionam
button.addEventListener('click', ...)
button.addEventListener('touchend', ...)  // Se necessário
```

### Font Readability
```
Viewport 320px:
  - Heading: 16px+ (não 12px)
  - Body: 14px+ (não 11px)
  - Min line-height: 1.5 (não 1.2)
```

---

## 🧪 Checklist de Testes

### Pré-requisitos
- [ ] Chrome DevTools aberto
- [ ] Responsive Design Mode ativado
- [ ] Network: "Fast 3G" (simular mobile lento)
- [ ] Device Pixel Ratio: 2.0 (retina)

### Publication Discovery
**320px Portrait:**
- [ ] Search input: 44px height ✅
- [ ] Placeholder visível
- [ ] Skeleton shimmer: fluido
- [ ] Cards: 1 coluna, legível
- [ ] Snippet: trunca se > 2 linhas?
- [ ] Buttons (Abrir, Citar): lado-a-lado ou empilhados?
  - **ESPERADO:** Empilhados verticalmente
- [ ] Margin/padding: sem overflow

**768px Landscape:**
- [ ] Cards: 2 colunas
- [ ] Espaço adequado
- [ ] Nenhum scroll horizontal

**1440px:**
- [ ] Cards: 3 colunas
- [ ] Baseline layout desktop

### Dataset Discovery
**320px Portrait:**
- [ ] Query input: acessível
- [ ] Project select: dropdown abre
- [ ] Filter button: 44px
- [ ] Table → Grid layout (não table em mobile!)
  - **CRÍTICO:** Tabelas não funcionam em 320px
- [ ] Cards: legível
- [ ] Pagination: "Anterior/Próxima" ou setas?
  - **ESPERADO:** Botões clicáveis

**768px:**
- [ ] Cards: 2 colunas
- [ ] Filtros: layout horizontal

**1440px:**
- [ ] Cards: 3 colunas
- [ ] Desktop padrão

### Territory Map
**320px:**
- [ ] Mapa: carrega
- [ ] Sidebar: visível ou escondido?
- [ ] Pan/zoom: touch funciona
- [ ] Controls: mapbox zoom buttons ok

**768px:**
- [ ] Mapa + sidebar: layout 2-column
- [ ] Touch: pinch zoom ok

**1440px:**
- [ ] Desktop layout

---

## 🐛 Bugs Encontrados (Template)

```markdown
### BUG #1: [Título]
**Viewport:** 320px Portrait
**Component:** Publication Discovery
**Descrição:**
  [O que está quebrado]
  
**Passos para reproduzir:**
  1. Abrir /publicacoes-discovery
  2. Redimensionar para 320px
  3. [O que faz quebrar]
  
**Resultado atual:**
  [O que acontece]
  
**Resultado esperado:**
  [O que deveria acontecer]
  
**Impacto:** [Alta/Média/Baixa]

**Fix:**
  [Arquivo: linha, mudança necessária]
```

---

## 📊 Métricas de Sucesso

| Viewport | Publication | Dataset | Territory | Dashboard | Status |
|----------|-------------|---------|-----------|-----------|--------|
| **320px** | ✅ Testado | ✅ Testado | ✅ Testado | ✅ Testado | ? |
| **768px** | ✅ Testado | ✅ Testado | ✅ Testado | ✅ Testado | ? |
| **1440px** | ✅ Testado | ✅ Testado | ✅ Testado | ✅ Testado | ? |

**Resultado:** Todos os viewports **sem bloqueadores críticos**

---

## 🚀 Executando Testes

### Setup Chrome DevTools
```javascript
// DevTools → Responsive Design Mode (Ctrl+Shift+M)
// Device: iPhone SE (375px) ou Custom (320px)
// Orientation: Portrait
// DPR: 2.0
```

### Testar cada componente
```bash
# 1. Publication Discovery
open https://homolog.neruds.org/publicacoes-discovery

# 2. Dataset Discovery
open https://homolog.neruds.org/datasets-discovery

# 3. Territory (exemplo)
open https://homolog.neruds.org/territorios/tocantins

# 4. Dashboard
open https://homolog.neruds.org/dashboard
```

### Screenshots para documentação
```bash
# Salvar screenshot de cada viewport/component
# Arquivo: docs/responsive-testing-[component]-[viewport].png
```

---

## 📋 Depois dos Testes

1. **Listar todos os bugs encontrados**
2. **Priorizar por impacto** (crítico → baixo)
3. **Estimar fixes** para cada bug
4. **Criar PRs** para fixes
5. **Re-testar** após fixes

---

## 🎯 Próximos Passos

- [ ] Completar testes em 3 viewports
- [ ] Documentar bugs encontrados
- [ ] Estimar tempo de fixes
- [ ] Passar para codex ou continuar com fixes

**Tempo esperado:** 24h (4 dias com 1 dev)  
**Início:** [Data]  
**Fim esperado:** [Data + 4 dias]
