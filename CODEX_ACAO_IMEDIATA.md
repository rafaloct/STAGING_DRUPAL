# ⚡ CODEX - AÇÃO IMEDIATA

**Situação:** Site retornando erro 500 em páginas de nó  
**Causa:** Sprint 1.4 (entity hooks) provavelmente causando o erro  
**Solução:** 3 comandos git

---

## 🚀 FAZER AGORA

### 1️⃣ Entrar no repo
```bash
cd /c/Users/Usuario/Documents/New\ project
```

### 2️⃣ Fazer revert de Sprint 1.4
```bash
git revert baf8e7a --no-edit
```

### 3️⃣ Push para remoto
```bash
git push origin feature/phase-1-blocker-critical
```

### 4️⃣ Testar em staging
```
Abrir: https://homolog.neruds.org/genero-e-poder-uma-analise-de-uma-diretoria-de-associacao-camponesa-no-norte-do-tocantins

Esperado: Página carrega SEM erro 500
```

---

## ✅ PRONTO!

Se erro 500 desaparecer, significa que era Sprint 1.4.

---

## 📚 DETALHES (Opcional, ler depois)

- `REVERT_SPRINT_1_4_INSTRUCTIONS.md` - Instruções completas
- `INCIDENT_REPORT_24MAI.md` - Análise do problema
- `STATUS_FINAL_24MAI.md` - Status geral

---

**Time:** Codex  
**Tempo:** ~5 minutos
