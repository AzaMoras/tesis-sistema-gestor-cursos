<?php

namespace Tests\Feature;

use App\Models\Inscripcion;
use DOMDocument;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Support\Facades\Route;
use Illuminate\Testing\TestResponse;
use RuntimeException;
use Tests\TestCase;

class PaginasErrorTest extends TestCase
{
    private const DETALLE_INTERNO = 'DETALLE_PRIVADO_07B';

    public function test_403_muestra_solo_los_mensajes_autorizados(): void
    {
        config(['app.debug' => false]);
        $mensajes = [
            'El aula requiere una inscripción confirmada y no cancelada.',
            'El curso o su institución no están disponibles. Tu historial se conserva.',
            'El acceso no está activado o ya venció. Puedes conservar y consultar tu historial.',
            'La configuración de vigencia está incompleta. Contacta a la institución.',
        ];

        foreach ($mensajes as $indice => $mensaje) {
            Route::get('/_test/errores/403/'.$indice, static fn () => abort(403, $mensaje));
            $this->verificarPagina($this->get('/_test/errores/403/'.$indice), 403, 'Acceso restringido')
                ->assertSeeText($mensaje);
        }

        Route::get('/_test/errores/403/interno', static fn () => abort(403, $mensajes[0].' '.self::DETALLE_INTERNO));
        $this->verificarPagina($this->get('/_test/errores/403/interno'), 403, 'Acceso restringido')
            ->assertSeeText('No tienes acceso a esta página o acción.')
            ->assertDontSee($mensajes[0]);

        Route::get('/_test/errores/403/cuenta', static fn () => abort(403, 'Tu cuenta no está activa.'));
        $this->verificarPagina($this->get('/_test/errores/403/cuenta'), 403, 'Acceso restringido')
            ->assertSeeText('No tienes acceso a esta página o acción.')
            ->assertDontSeeText('Tu cuenta no está activa.');
    }

    public function test_404_oculta_el_modelo_y_el_identificador_y_no_distingue_un_recurso_fuera_de_alcance(): void
    {
        config(['app.debug' => false]);
        Route::get('/_test/errores/404/recurso', static function (): never {
            throw (new ModelNotFoundException)->setModel(Inscripcion::class, [812345]);
        });

        $ausente = $this->verificarPagina($this->get('/_test/errores/404/ausente'), 404, 'Página no encontrada');
        $recurso = $this->verificarPagina($this->get('/_test/errores/404/recurso'), 404, 'Página no encontrada')
            ->assertDontSee(Inscripcion::class, false)
            ->assertDontSee('812345');

        self::assertSame($ausente->getContent(), $recurso->getContent());
    }

    public function test_419_se_renderiza_cuando_el_middleware_real_rechaza_un_post_sin_token(): void
    {
        config(['app.debug' => false, 'session.driver' => 'array']);

        // Se conserva la validación nativa y solo se quita la omisión de CSRF en testing.
        $this->app->bind(PreventRequestForgery::class, static fn ($app) => new class($app, $app['encrypter']) extends PreventRequestForgery
        {
            protected function runningUnitTests()
            {
                return false;
            }
        });

        $this->verificarPagina($this->post('/login?next=https://externo.test'), 419, 'El formulario expiró')
            ->assertSeeText('abre de nuevo el formulario')
            ->assertDontSee('externo.test')
            ->assertDontSee('<form', false);
    }

    public function test_422_http_explicito_y_la_validacion_web_tienen_respuestas_distintas(): void
    {
        config(['app.debug' => false, 'session.driver' => 'array']);
        Route::get('/_test/errores/422', static fn () => abort(422, self::DETALLE_INTERNO));
        Route::post('/_test/errores/formulario', static fn () => request()->validate(
            ['campo' => 'required'],
            ['campo.required' => 'Completa el campo.'],
        ))->middleware('web');

        $this->verificarPagina($this->get('/_test/errores/422'), 422, 'No se pudo procesar la solicitud')
            ->assertSeeText('Revisa la información');
        $this->from('/')->post('/_test/errores/formulario')
            ->assertRedirect('/')
            ->assertSessionHasErrors(['campo' => 'Completa el campo.']);
    }

    public function test_500_sin_depuracion_no_necesita_sesion_usuario_base_de_datos_ni_vite(): void
    {
        config([
            'app.debug' => false,
            'database.default' => 'sin-conexion',
            'database.connections' => [],
            'session.driver' => 'array',
        ]);
        $this->app->usePublicPath(base_path('tests/__publico_inexistente_07b'));
        self::assertFileDoesNotExist(public_path('build/manifest.json'));

        Route::get('/_test/errores/500', static function (): never {
            throw new RuntimeException(self::DETALLE_INTERNO.' SELECT campo FROM tabla_privada');
        });

        // La ruta usa el kernel HTTP sin el grupo web que inicia la sesión.
        $respuesta = $this->get('/_test/errores/500');
        self::assertFalse($respuesta->baseRequest->hasSession());
        self::assertNull($respuesta->baseRequest->user());

        $this->verificarPagina($respuesta, 500, 'Ocurrió un error inesperado')
            ->assertSeeText('inténtalo más tarde')
            ->assertDontSee('tabla_privada')
            ->assertDontSee('RuntimeException')
            ->assertDontSee(__FILE__, false);
    }

    private function verificarPagina(TestResponse $respuesta, int $estado, string $titulo): TestResponse
    {
        $respuesta->assertStatus($estado)
            ->assertSeeText('Error '.$estado)
            ->assertSeeText($titulo)
            ->assertSeeText('Volver al inicio')
            ->assertDontSee(self::DETALLE_INTERNO);

        $documento = new DOMDocument;
        self::assertTrue($documento->loadHTML($respuesta->getContent(), LIBXML_NOERROR | LIBXML_NOWARNING | LIBXML_NONET));
        self::assertSame('es-MX', $documento->documentElement->getAttribute('lang'));
        self::assertSame($titulo, $documento->getElementsByTagName('h1')->item(0)?->textContent);
        $enlaces = $documento->getElementsByTagName('a');
        self::assertCount(2, $enlaces);
        foreach ($enlaces as $enlace) {
            self::assertSame(route('inicio'), $enlace->getAttribute('href'));
        }

        return $respuesta;
    }
}
