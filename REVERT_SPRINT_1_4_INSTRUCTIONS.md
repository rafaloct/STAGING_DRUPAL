# 🔄 INSTRUÇÕES: Reverter Sprint 1.4

**Data:** 24 mai 2026  
**Motivo:** Erro 500 em páginas de nó - causa provável: entity hooks  
**Commit a reverter:** `baf8e7a` - feat: Implement Drupal to Google Cloud sync

---

## 📋 RESUMO

Sprint 1.4 implementou sincronização Drupal ↔ Google Cloud via entity hooks. Esses hooks podem estar causando erro 500 ao carregar páginas de nó (publicações/documentos).

---

## ✅ PASSO 1: VERIFICAR STATUS

```bash
# Entrar no repo
cd /c/Users/Usuario/Documents/New\ project

# Ver branch atual
git branch -v

# Ver últimos commits
git log --oneline -5

# Esperado:
# feature/phase-1-blocker-critical (com todos os commits)
```

---

## 🔄 PASSO 2: FAZER REVERT

```bash
# OPÇÃO A: Revert (cria novo commit que desfaz as mudanças)
git revert baf8e7a --no-edit

# OPÇÃO B: Reset (remove o commit - mais agressivo)
# git reset --soft baf8e7a^
# git reset --hard baf8e7a^

# Recomendação: Use OPÇÃO A (revert) para manter histórico limpo
```

---

## ✅ PASSO 3: VERIFICAR O REVERT

```bash
# Ver novo commit de revert
git log --oneline -1

# Esperado output:
# [novo hash] Revert "feat: Implement Drupal to Google Cloud sync"

# Ver mudanças
git diff HEAD^ HEAD --stat

# Esperado: Removidas as 305 linhas do Sprint 1.4
```

---

## ✅ PASSO 4: FAZER PUSH

```bash
# Push para remoto
git push origin feature/phase-1-blocker-critical

# Ou, se quiser empurrar para staging para validar:
git checkout main
git pull origin main
git merge feature/phase-1-blocker-critical
git push origin main
```

---

## ✅ PASSO 5: VALIDAR NO NAVEGADOR

```bash
# Depois que o código está em staging/produção:
# Abrir a página que dava erro
open https://homolog.neruds.org/genero-e-poder-uma-analise-de-uma-diretoria-de-associacao-camponesa-no-norte-do-tocantins

# Esperado: Página carrega sem erro 500
```

---

## 📊 O QUE SERÁ PERDIDO

Ao reverter Sprint 1.4, **essas funcionalidades são desativadas:**

```
❌ Sincronização automática Drupal → BigQuery
❌ Hook_entity_insert (quando novo conteúdo criado)
❌ Hook_entity_update (quando conteúdo editado)
❌ Hook_entity_delete (quando conteúdo deletado)
❌ Hook_cron (processamento de fila)
❌ Queue worker NerudsGoogleSync
❌ Métodos syncNodeToBigQuery() e markNodeDeletedInBigQuery()
```

**Nota:** As outras funcionalidades CONTINUAM ativas:
- ✅ Sprint 1.1: Paginação
- ✅ Sprint 1.2: Rate Limiting
- ✅ Sprint 1.3: BigQuery Optimization
- ✅ Sprint 2.1: Skeleton Screens
- ✅ Sprint 2.3: Retry & Backoff

---

## 🔧 SE ALGO DER ERRADO

### Se o revert falhar:

```bash
# Cancelar revert
git revert --abort

# Ou fazer reset
git reset --hard HEAD~1
```

### Se quiser reverter o revert:

```bash
# Ver commits recentes
git log --oneline -5

# Fazer revert do revert (volta ao estado anterior)
git revert [hash do commit de revert]
```

### Se receber erro de merge:

```bash
# Resolver conflicts manualmente
git status  # Ver conflitos

# Editar arquivos com <<<<<<
# Depois:
git add [arquivo]
git revert --continue
```

---

## 📝 PRÓXIMAS AÇÕES

### Imediato:
1. ✅ Fazer revert
2. ✅ Push para remoto
3. ✅ Validar em staging (erro 500 resolvido?)

### Depois:
1. **Investigar a raiz causa** do erro 500
   - Era realmente os hooks?
   - Qual hook específico causava?
   - Falta alguma validação?

2. **Implementar Sprint 1.4 novamente** (quando tiver tempo)
   - Com validação melhorada
   - Com testes
   - Com fallback se falhar

3. **Documentar a lição aprendida**
   - O que causou o erro?
   - Como prevenir no futuro?

---

## 🎯 CHECKLIST

- [ ] Branch correto: `feature/phase-1-blocker-critical`
- [ ] Git status limpo (nada uncommitted)
- [ ] Fazer revert com: `git revert baf8e7a --no-edit`
- [ ] Novo commit criado com histórico do revert
- [ ] Push para remoto
- [ ] Validar em staging (erro 500 gone?)
- [ ] Documentar na próxima revisão

---

## 📞 SUPORTE

**Dúvidas:**
- Ver `git revert --help`
- Slack: #desenvolvimento
- Docs: `STATUS_FINAL_24MAI.md`

---

**Feito por:** Claude Code  
**Para:** Codex  
**Data:** 24 mai 2026 22:06

---

**RESUMO RÁPIDO:**
```bash
git revert baf8e7a --no-edit
git push origin feature/phase-1-blocker-critical
# Done! Erro 500 deve estar resolvido.
```
