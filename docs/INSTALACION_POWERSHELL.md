# Instalación paso a paso en Windows PowerShell / Antigravity

**No ejecutar los comandos si existen archivos con el mismo nombre sin revisarlos antes.**

1. Abrir el proyecto en Antigravity y la terminal PowerShell.

```powershell
cd "$HOME\tesis-sistema-gestor-cursos"
git status
Get-ChildItem .\resources\views\layouts\
```

2. Crear una rama cuando el árbol de trabajo esté limpio:

```powershell
git switch -c feature/layouts-blade
```

3. Descargar y descomprimir este ZIP. Copiar los directorios `resources/views/layouts/`, `resources/views/partials/`, `resources/views/components/`, `resources/views/tesis/` a sus equivalentes del repositorio. Copiar `public/css/tesis-ui.css`. **No sustituir el `routes/web.php` existente.**

4. Si quieres activar las rutas de prueba, añade en `routes/web.php`:

```php
require __DIR__.'/tesis_vistas_demo.php';
```

   Y copia `routes/tesis_vistas_demo.php`. Las rutas privadas requieren autenticación instalada; deberán añadirse los permisos correspondientes antes de producción.

5. Verificar:

```powershell
php artisan view:clear
php artisan route:list
php artisan serve
```

   Abre `http://127.0.0.1:8000/_demo-tesis` y `http://127.0.0.1:8000/_demo-tesis/cursos`.

6. Copiar `README.md` **solo tras comparar** con el actual. Puede guardarse como `README_PROPUESTA.md` mientras el equipo lo valida.

7. Revisar cambios y crear commit:

```powershell
git status
git diff --stat
git add resources/views public/css/tesis-ui.css docs README.md routes/tesis_vistas_demo.php
git commit -m "feat(ui): agregar plantillas Blade y guia visual"
git push -u origin feature/layouts-blade
```

La operación de `git add` debe adaptarse a las rutas realmente copiadas; nunca hacer commit de archivos de entorno o credenciales.
