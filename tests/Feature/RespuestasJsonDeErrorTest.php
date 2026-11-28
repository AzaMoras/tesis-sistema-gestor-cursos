<?php

namespace Tests\Feature;

use App\Models\Inscripcion;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Support\Facades\Route;
use PHPUnit\Framework\Attributes\DataProvider;
use Psr\Log\LoggerInterface;
use RuntimeException;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

class RespuestasJsonDeErrorTest extends TestCase
{
    private const DETALLE_INTERNO = 'Detalle interno sintetico 07B';

    public function test_el_404_de_modelo_y_el_de_ruta_tienen_el_mismo_cuerpo_publico(): void
    {
        config(['app.debug' => false]);
        Route::get('/_test/07b/modelo', static function (): never {
            throw (new ModelNotFoundException)->setModel(Inscripcion::class, [812345]);
        });

        $ruta = $this->getJson('/_test/07b/ausente')->assertNotFound()
            ->assertExactJson(['message' => 'Página no encontrada.']);
        $modelo = $this->getJson('/_test/07b/modelo')->assertNotFound();

        self::assertSame($ruta->getContent(), $modelo->getContent());
    }

    public function test_el_403_conserva_solo_coincidencias_exactas_de_mensajes_publicos(): void
    {
        config(['app.debug' => true]);
        $mensajes = [
            'Tu cuenta no está activa.',
            'El aula requiere una inscripción confirmada y no cancelada.',
            'El curso o su institución no están disponibles. Tu historial se conserva.',
            'El acceso no está activado o ya venció. Puedes conservar y consultar tu historial.',
            'La configuración de vigencia está incompleta. Contacta a la institución.',
        ];

        foreach ($mensajes as $indice => $mensaje) {
            Route::get('/_test/07b/permitido/'.$indice, static fn () => abort(403, $mensaje));
            Route::get('/_test/07b/no-permitido/'.$indice, static fn () => abort(403, $mensaje.' '.self::DETALLE_INTERNO));

            $this->getJson('/_test/07b/permitido/'.$indice)->assertForbidden()
                ->assertExactJson(['message' => $mensaje]);
            $this->getJson('/_test/07b/no-permitido/'.$indice)->assertForbidden()
                ->assertExactJson(['message' => 'No tienes acceso a esta página o acción.']);
        }
    }

    #[DataProvider('excepcionesHttp')]
    public function test_las_excepciones_http_descartan_detalles_y_conservan_encabezados(
        int $estado,
        string $mensaje,
        array $encabezados,
    ): void {
        config(['app.debug' => true]);
        Route::get('/_test/07b/http', static fn () => abort($estado, self::DETALLE_INTERNO, $encabezados + [
            'ETag' => '"original"',
            'Content-Length' => '99999',
            'Content-Digest' => 'sha-256=:b3JpZ2luYWw=:',
        ]));

        $respuesta = $this->getJson('/_test/07b/http')->assertStatus($estado)
            ->assertHeader('Content-Type', 'application/json')
            ->assertExactJson(['message' => $mensaje])
            ->assertHeaderMissing('ETag')
            ->assertHeaderMissing('Content-Length')
            ->assertHeaderMissing('Content-Digest');

        foreach ($encabezados as $nombre => $valor) {
            $respuesta->assertHeader($nombre, $valor);
        }
    }

    public static function excepcionesHttp(): array
    {
        return [
            '401' => [401, 'Unauthenticated.', ['WWW-Authenticate' => 'Bearer']],
            '405' => [405, 'Método no permitido.', ['Allow' => 'GET, HEAD']],
            '419' => [419, 'CSRF token mismatch.', []],
            '422' => [422, 'No se pudo procesar la solicitud.', []],
            '429' => [429, 'Too Many Attempts.', ['Retry-After' => '60', 'X-RateLimit-Limit' => '1', 'X-RateLimit-Remaining' => '0']],
            '451' => [451, 'No se pudo procesar la solicitud.', ['Link' => '<https://example.test/aviso>; rel="blocked-by"']],
            '503' => [503, 'Ocurrió un error inesperado.', ['Retry-After' => '120']],
        ];
    }

    #[DataProvider('modosDepuracion')]
    public function test_el_405_del_enrutador_conserva_allow_y_oculta_el_detalle(bool $debug): void
    {
        config(['app.debug' => $debug]);
        Route::get('/_test/07b/metodo', static fn () => response()->noContent());

        $this->postJson('/_test/07b/metodo')->assertStatus(405)
            ->assertHeader('Allow', 'GET, HEAD')
            ->assertExactJson(['message' => 'Método no permitido.']);
    }

    #[DataProvider('modosDepuracion')]
    public function test_el_500_es_generico_y_reporta_la_excepcion_original_una_vez(bool $debug): void
    {
        config(['app.debug' => $debug]);
        $excepcion = new RuntimeException(self::DETALLE_INTERNO);
        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects($this->once())->method('error')->with(
            self::DETALLE_INTERNO,
            $this->callback(static fn (array $contexto): bool => ($contexto['exception'] ?? null) === $excepcion),
        );
        $this->app->instance(LoggerInterface::class, $logger);
        Route::get('/_test/07b/fallo', static fn () => throw $excepcion);

        $this->getJson('/_test/07b/fallo')->assertStatus(500)
            ->assertExactJson(['message' => 'Ocurrió un error inesperado.']);
    }

    public static function modosDepuracion(): array
    {
        return ['apagada' => [false], 'activa' => [true]];
    }

    public function test_validacion_json_y_html_conservan_sus_contratos(): void
    {
        config(['app.debug' => true, 'session.driver' => 'array']);
        Route::post('/_test/07b/formulario', static fn () => request()->validate(
            ['campo' => 'required'],
            ['campo.required' => 'Completa el campo.'],
        ))->middleware('web');

        $this->postJson('/_test/07b/formulario')->assertUnprocessable()->assertExactJson([
            'message' => 'Completa el campo.',
            'errors' => ['campo' => ['Completa el campo.']],
        ]);
        $this->from('/')->post('/_test/07b/formulario')->assertRedirect('/')
            ->assertSessionHasErrors(['campo' => 'Completa el campo.']);
    }

    public function test_las_respuestas_json_explicitas_conservan_sus_datos(): void
    {
        $datos = ['message' => 'Respuesta de la aplicación.', 'data' => ['id' => 42]];
        Route::get('/_test/07b/json', static fn () => response()->json($datos, 422));
        Route::get('/_test/07b/json-explicito', static fn () => throw new HttpResponseException(response()->json($datos, 429)));

        $this->getJson('/_test/07b/json')->assertUnprocessable()->assertExactJson($datos);
        $this->getJson('/_test/07b/json-explicito')->assertStatus(429)->assertExactJson($datos);
    }

    public function test_una_excepcion_con_render_conserva_su_respuesta_personalizada(): void
    {
        Route::get('/_test/07b/render-propio', static function (): never {
            throw new class(self::DETALLE_INTERNO) extends HttpException
            {
                public function __construct(string $mensaje)
                {
                    parent::__construct(405, $mensaje);
                }

                public function render(): \Illuminate\Http\JsonResponse
                {
                    return response()->json(['message' => 'Respuesta personalizada.'], 405, ['Allow' => 'POST']);
                }
            };
        });

        $this->getJson('/_test/07b/render-propio')->assertStatus(405)
            ->assertHeader('Allow', 'POST')
            ->assertExactJson(['message' => 'Respuesta personalizada.']);
    }

    #[DataProvider('modosDepuracion')]
    public function test_una_excepcion_con_render_que_delega_recibe_el_mensaje_publico(bool $debug): void
    {
        config(['app.debug' => $debug]);
        Route::get('/_test/07b/render-delegado/{estado}', static function (string $estado): never {
            throw new class((int) $estado, self::DETALLE_INTERNO, headers: ['Allow' => 'POST']) extends HttpException
            {
                public function render(): false
                {
                    return false;
                }
            };
        });

        $this->getJson('/_test/07b/render-delegado/405')->assertStatus(405)
            ->assertHeader('Allow', 'POST')
            ->assertExactJson(['message' => 'Método no permitido.']);
        $this->getJson('/_test/07b/render-delegado/500')->assertStatus(500)
            ->assertExactJson(['message' => 'Ocurrió un error inesperado.']);
    }

    public function test_autenticacion_csrf_y_html_conservan_su_comportamiento(): void
    {
        config(['app.debug' => false, 'session.driver' => 'array']);
        $this->getJson('/mi-cuenta')->assertUnauthorized()->assertExactJson(['message' => 'Unauthenticated.']);
        $this->get('/mi-cuenta')->assertRedirect(route('login'));
        $this->get('/_test/07b/ausente')->assertNotFound()->assertSee('Página no encontrada');

        $this->app->bind(PreventRequestForgery::class, static fn ($app) => new class($app, $app['encrypter']) extends PreventRequestForgery
        {
            protected function runningUnitTests()
            {
                return false;
            }
        });

        $this->postJson('/login')->assertStatus(419)->assertExactJson(['message' => 'CSRF token mismatch.']);
        $this->post('/login')->assertStatus(419)->assertSee('El formulario expiró');
    }

    public function test_el_limitador_nativo_conserva_el_mensaje_y_los_encabezados(): void
    {
        config(['cache.default' => 'array']);
        Route::get('/_test/07b/limite', static fn () => response()->json(['ok' => true]))->middleware('throttle:1,1');

        $this->getJson('/_test/07b/limite')->assertOk();
        $this->getJson('/_test/07b/limite')->assertStatus(429)
            ->assertExactJson(['message' => 'Too Many Attempts.'])
            ->assertHeader('Retry-After')
            ->assertHeader('X-RateLimit-Limit', '1')
            ->assertHeader('X-RateLimit-Remaining', '0');
    }
}
