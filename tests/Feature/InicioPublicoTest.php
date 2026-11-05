<?php

namespace Tests\Feature;

use DOMDocument;
use DOMXPath;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class InicioPublicoTest extends TestCase
{
    public function test_inicio_es_publico_y_muestra_la_landing_sin_oferta_ficticia(): void
    {
        $this->get(route('inicio'))
            ->assertOk()
            ->assertViewIs('publico.inicio')
            ->assertSee('Aprende algo nuevo.')
            ->assertSee('FORMACIÓN 100 % VIRTUAL')
            ->assertSee('El catálogo aún no está disponible.')
            ->assertSee('canales oficiales de contacto')
            ->assertDontSee('Diseño web desde cero')
            ->assertDontSee('64 % completado')
            ->assertDontSee('href="'.url('/login').'"', false);
    }

    public function test_enlaces_de_la_landing_resuelven_y_sus_anclas_existen(): void
    {
        $document = new DOMDocument;
        @$document->loadHTML('<?xml encoding="UTF-8">'.$this->get('/')->assertOk()->getContent());
        $xpath = new DOMXPath($document);
        $links = $xpath->query('//a[@href]');
        self::assertGreaterThan(10, $links->length);

        foreach ($links as $link) {
            $href = $link->getAttribute('href');
            $path = parse_url($href, PHP_URL_PATH) ?: '/';
            self::assertSame('/', $path, $href);
            $fragment = parse_url($href, PHP_URL_FRAGMENT);
            if ($fragment !== null) {
                self::assertSame(1, $xpath->query('//*[@id="'.$fragment.'"]')->length, $href);
            }
            $this->get($path)->assertOk();
        }
    }

    public function test_selector_de_tema_esta_fuera_del_menu_y_recursos_existen(): void
    {
        $document = new DOMDocument;
        @$document->loadHTML($this->get('/')->assertOk()->getContent());
        $xpath = new DOMXPath($document);
        self::assertSame(1, $xpath->query('//button[@data-theme-toggle and not(ancestor::details)]')->length);
        self::assertFileExists(public_path('js/tema-publico.js'));
        self::assertFileExists(public_path('css/tesis-ui.css'));
        self::assertFileExists(public_path('images/estudio-virtual.jpg'));
    }

    public function test_demostraciones_publicas_siguen_disponibles(): void
    {
        $this->get('/_demo-tesis')->assertOk()->assertViewIs('tesis.inicio');
        $this->get('/_demo-tesis/cursos')->assertOk()->assertViewIs('tesis.catalogo');
    }

    public function test_acceso_pendiente_es_visible_sin_enlace_roto_y_separado_del_tema(): void
    {
        $document = new DOMDocument;
        @$document->loadHTML($this->get('/')->assertOk()->getContent());
        $xpath = new DOMXPath($document);

        self::assertSame(1, $xpath->query('//button[@disabled and @aria-describedby="acceso-publico-estado" and not(ancestor::details)]')->length);
        self::assertSame(1, $xpath->query('//*[@id="acceso-publico-estado"]')->length);
        self::assertSame(0, $xpath->query('//a[contains(@href, "/login")]')->length);
    }

    public function test_componente_de_acceso_usa_una_ruta_nominal_cuando_esta_disponible(): void
    {
        // Ruta aislada de esta prueba; no implementa autenticación en la aplicación.
        Route::get('/acceso-prueba', fn () => 'Acceso de prueba')->name('login');
        Route::getRoutes()->refreshNameLookups();

        $this->blade('<x-acceso-publico />')
            ->assertSee('href="'.route('login').'"', false)
            ->assertSee('Iniciar sesión')
            ->assertDontSee('disabled')
            ->assertDontSee('Próximamente');
        $this->get(route('login'))->assertOk();
    }

    public function test_paneles_privados_siguen_exigiendo_autenticacion(): void
    {
        foreach (['/_demo-tesis/estudiante', '/_demo-tesis/estudiante/1', '/_demo-tesis/privado', '/_demo-tesis/privado/1'] as $path) {
            $this->get($path)->assertUnauthorized();
        }
    }

    public function test_no_se_habilita_registro_ni_autenticacion_publica(): void
    {
        $this->get('/login')->assertNotFound();
        $this->get('/register')->assertNotFound();
        $this->post('/register', [])->assertNotFound();
    }
}
