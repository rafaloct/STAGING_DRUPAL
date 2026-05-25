# 📋 PRÓXIMOS PASSOS - Fase 2.2 até Fase 3

**Data:** 24 mai 2026 22:02  
**Status atual:** Fase 1+2 COMPLETAS, prontas para staging/produção  
**Foco:** Sprint 2.2 (responsiveness) OU Fase 3 (security)  
**Timeline:** 6 semanas até produção

---

## 🎯 3 Caminhos Possíveis

### CAMINHO 1: Merge para Staging (RECOMENDADO - 2h)
**Se:** Confiante nas implementações, quer validar em staging
**Tempo:** ~2h (merge + validação)

```bash
# 1. Fazer merge
git checkout main
git pull origin main
git merge feature/phase-1-blocker-critical
git push origin main

# 2. Validar em staging
## Pagination
open https://homolog.neruds.org/datasets-discovery
# → Clicar em dataset
# → Testar paginação (página 1 → 2)

## Rate limiting
# → DevTools Network
# → Verificar header: X-RateLimit-Limit
# → Expected: 60 ou 120

## Skeleton screens
# → Abrir /publicacoes-discovery
# → Fazer busca
# → Ver shimmer animation por 1-2s

## Retry backoff
# → DevTools Network
# → Simular "Slow 3G"
# → Fazer request
# → Verificar 3 tentativas se falhar
```

**Próximo:** Sprint 2.2 ou Fase 3

---

### CAMINHO 2: Sprint 2.2 Responsiveness Testing (LONGO - 24h)
**Se:** Tempo para testar mobile, quer experiência perfeita
**Tempo:** 24 horas (4 dias full-time)

```bash
# 1. Abrir referência
code PHASE_2_SPRINT_2_2_RESPONSIVENESS_TESTING.md

# 2. Setup Chrome DevTools
# DevTools → F12
# Responsive Design Mode → Ctrl+Shift+M
# Device: iPhone SE (375px) ou Custom (320px)

# 3. Testar cada viewport
## 320px (mobile)
open https://homolog.neruds.org/publicacoes-discovery
open https://homolog.neruds.org/datasets-discovery
open https://homolog.neruds.org/territorios/tocantins
open https://homolog.neruds.org/dashboard

## 768px (tablet)
# Redimensionar

## 1440px (desktop)
# Redimensionar

# 4. Documentar bugs encontrados
# Arquivo: BUGS_ENCONTRADOS.md (criar)
# Template em Sprint 2.2 docs

# 5. Implementar fixes
# Criar branch: feature/phase-2-2-responsiveness-fixes
git checkout -b feature/phase-2-2-responsiveness-fixes

# 6. Committar e fazer PR

# 7. Merge para main (após aprovação)
```

**Tempo por componente:**
- Publication Discovery: 4h
- Dataset Discovery: 4h
- Territory Map: 4h
- Dashboard: 4h
- Fixes baseado em bugs: 8h
- **Total: 24h**

**Próximo:** Fase 3 (após testes)

---

### CAMINHO 3: Fase 3 Security & DevOps (IMPORTANTE - 10 dias)
**Se:** Pronto para trabalhar em security/keys/RBAC
**Tempo:** 10 dias (6 sprints)

```bash
# 1. Ler Strategic Plan
code NERUDS_PORTAL_AUDIT_AND_STRATEGIC_PLAN.md
# Seção: FASE 3 (linha ~594)

# 2. Criar branch
git checkout -b feature/phase-3-security

# 3. Sprint 3.1: Input Validation (2 dias)
# - Audit todas APIs para injeção
# - Sanitizar search queries
# - Fix BigQuery prepared statements

# 4. Sprint 3.2: Key Rotation (2 dias)
# - Setup Google Secret Manager
# - Implementar rotação mensal de keys
# - Monitorar acessos

# 5. Sprint 3.3: RBAC (2 dias)
# - Implementar permission checks
# - Audit logs para mutations
# - API access control

# 6. Sprint 3.4: Resilience Testing (4 dias)
# - Load testing com 1000+ usuários
# - Chaos testing (falhas deliberadas)
# - Monitor SLAs
```

**Documentação:** Criar `FASE_3_README.md` com mesmo padrão que Fase 1+2

**Próximo:** Fase 4 (após security)

---

## ✅ Recomendação

```
ORDEM SUGERIDA:
1. Hoje: Finish current branch (22:50 deadline)
2. Amanhã: Caminho 1 (Merge para staging) - 2h
3. Próximos 2 dias: Caminho 2 (Sprint 2.2 testing) - 24h
4. Semana que vem: Caminho 3 (Fase 3) - 10 dias
5. Semana 4: Fase 4 (data integrity) - 10 dias
6. Semana 5-6: Fase 5+6 (features + testing) - 10 dias

Total: 6 semanas → PRODUÇÃO PRONTA ✅
```

---

## 📊 Métricas de Sucesso por Fase

### Fase 2.2 (Responsiveness)
```
✅ Todos viewports (320/768/1440) testados
✅ 4 componentes sem bloqueadores críticos
✅ 44px minimum touch targets validados
✅ Font readability em 320px confirmada
❓ Bugs encontrados e fixados
```

### Fase 3 (Security)
```
✅ Zero SQL injection vulnerabilidades
✅ Input validation em todas APIs
✅ Keys rotacionadas automaticamente
✅ RBAC implementado
✅ Audit logs funcionando
✅ Load test: 99.9% uptime em picos
```

---

## 🚨 Riscos Conhecidos

### Risco 1: Drupal Sync em Produção
**Mitigação:** Testar cron job em staging antes de produção

### Risco 2: Mobile Responsiveness
**Mitigação:** Sprint 2.2 testing abrangente (24h)

### Risco 3: Security Compliance
**Mitigação:** Fase 3 antes de produção (não pular)

---

## 📚 Documentação de Referência

```
FASE 1:
  ✅ FASE_1_README.md
  ✅ PHASE_1_SPRINT_1_*.md (4 sprints)

FASE 2:
  ✅ FASE_2_README.md
  ✅ PHASE_2_SPRINT_2_*.md (3 sprints)
  ❓ BUGS_ENCONTRADOS.md (criar durante 2.2)

FASE 3:
  ❓ FASE_3_README.md (criar)
  ❓ PHASE_3_SPRINT_3_*.md (criar)

EXECUTIVO:
  ✅ START_HERE.md
  ✅ EXECUTIVE_SUMMARY.txt
  ✅ NERUDS_PORTAL_AUDIT_AND_STRATEGIC_PLAN.md
```

---

## 🔗 Links Rápidos

**Merge para staging:**
```bash
git checkout main && git merge feature/phase-1-blocker-critical
```

**Ver todos commits:**
```bash
git log feature/phase-1-blocker-critical --oneline -12
```

**Ver mudanças vs main:**
```bash
git diff main...feature/phase-1-blocker-critical --stat
```

**Criar branch para próxima fase:**
```bash
git checkout -b feature/phase-X-[nome]
```

---

## ⏰ Timeline Realista

```
✅ Dia 0 (24 mai):   Fase 1+2 COMPLETA
   Dia 1 (25 mai):   Merge + Staging Validation
   Dia 2-4 (26-28):  Sprint 2.2 Testing (24h)
   Dia 5-16:         Fase 3 Security (10 dias)
   Dia 17-27:        Fase 4 Data Integrity (10 dias)
   Dia 28-39:        Fase 5 Features (10 dias)
   Dia 40-42:        Fase 6 Testing & Launch (3 dias)
   
RESULTADO: Dia 42 → PRODUÇÃO READY ✅
```

**Data esperada:** ~5 de julho 2026 (6 semanas)

---

## 👥 Responsabilidades

**Codex:**
- [ ] Revisar este documento
- [ ] Escolher caminho (1, 2, ou 3)
- [ ] Comunicar timeline aos stakeholders
- [ ] Executar próximo sprint

**Monitoramento:**
- [ ] Metrics dashboard (uptime, latência, custos)
- [ ] Alert se rate limit > 10%
- [ ] Log de retries/falhas

---

**Preparado por:** Claude Code  
**Data:** 24 mai 2026 22:02  
**Status:** Pronto para handoff ✅
