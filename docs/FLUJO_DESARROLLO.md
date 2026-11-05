# Flujo de trabajo con VS Code, Codex y GitHub

## Antes de trabajar
En PowerShell, dentro del repositorio Laravel:

```powershell
git rev-parse --show-toplevel
git status --short
git branch --show-current
git remote -v
```

Si hay cambios locales pendientes, revisarlos y preservarlos antes de actualizar o cambiar de rama.
Con árbol limpio:

```powershell
git fetch origin
git switch main
git pull --ff-only origin main
git switch -c feature/nombre-funcionalidad
```

Si la rama ya existe, reutilizarla conscientemente; no ejecutar `switch -c` con el mismo nombre.

## En Codex
- Trabajar en modo local, sobre la misma carpeta y rama visibles en VS Code.
- Leer `AGENTS.md`, `docs/CONTEXTO_PROYECTO.md`, `docs/REGLAS_NEGOCIO.md` y los archivos específicos de la tarea.
- Implementar una funcionalidad acotada por sesión y guardar los archivos en el proyecto.
- No autorizar commits, pushes, merges ni operaciones destructivas automáticas.

## Verificar
```powershell
git status --short
git diff --check
git diff --stat
php artisan route:list
```

Solo si el ambiente de testing es seguro, aislado y está configurado:

```powershell
php artisan test
```

Verificar manualmente el flujo en navegador cuando sea un cambio de interfaz. Revisar los resultados reales y los casos fallidos. Corregir antes del commit.

## Commit y push
Preparar únicamente los archivos de la funcionalidad. Ejemplo:

```powershell
git add ruta/archivo1 ruta/archivo2
git diff --cached --stat
git diff --cached
git commit -m "feat(cursos): implementar catalogo publico"
git push -u origin HEAD
```

Abrir Pull Request de la rama hacia `main`, revisar archivos y pruebas, hacer merge conservando commits individuales. Después actualizar la copia local:

```powershell
git switch main
git pull --ff-only origin main
```

## Evidencia para el profesor
Mantener commits pequeños y descriptivos, PRs revisables, capturas de las funcionalidades, resultados de pruebas, y un historial de documentación LaTeX separado cuando corresponda.
No hacer commits vacíos solo para aparentar actividad.
