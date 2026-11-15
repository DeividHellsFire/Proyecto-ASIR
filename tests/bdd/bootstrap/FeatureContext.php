<?php

namespace GymTrack\Tests\bdd\bootstrap;

use Behat\Behat\Context\Context;

class FeatureContext implements Context
{
    private string $html = '';

    private static bool $initialized = false;

    public function __construct()
    {
        require_once '/var/www/html/wp-load.php';

        wp_set_current_user(1);

        if (!self::$initialized) {
            add_filter(
                'pre_http_request',
                function ($pre, $args, $url) {
                    if (
                        false === strpos(
                            $url,
                            'wger.de/api/v2/exerciseinfo/'
                        )
                    ) {
                        return $pre;
                    }

                    parse_str(
                        (string) parse_url($url, PHP_URL_QUERY),
                        $query
                    );

                    $term = strtolower(
                        $query['name__search'] ?? ''
                    );

                    $results = array();

                    if ('press banca' === $term) {
                        $results[] = array(
                            'id' => 123,
                            'name' => 'Press banca',
                            'description' => 'Ejercicio de entrenamiento de pecho.',
                            'category' => array(
                                'name' => 'Pecho',
                            ),
                            'translations' => array(),
                        );
                    }

                    return array(
                        'headers' => array(),
                        'body' => wp_json_encode(
                            array(
                                'count' => count($results),
                                'results' => $results,
                            )
                        ),
                        'response' => array(
                            'code' => 200,
                            'message' => 'OK',
                        ),
                        'cookies' => array(),
                        'filename' => null,
                    );
                },
                10,
                3
            );

            self::$initialized = true;
        }
    }

    /**
     * @Given que estoy en la biblioteca de ejercicios
     */
    public function estoyEnLaBiblioteca(): void
    {
        $this->html = '';
    }

    /**
     * @When busco :term
     */
    public function busco(string $term): void
    {
        $this->executeSearch($term);
    }

    /**
     * @When realizo una búsqueda sin introducir ningún término
     */
    public function buscoSinTermino(): void
    {
        $this->executeSearch('');
    }

    /**
     * @Then se muestran los ejercicios coincidentes
     */
    public function seMuestranLosEjercicios(): void
    {
        $this->assertContains('Press banca');
    }

    /**
     * @Then se muestran sus datos e información disponible
     */
    public function seMuestranSusDatos(): void
    {
        $this->assertContains('123');
        $this->assertContains('Pecho');
    }

    /**
     * @Then se muestra el mensaje :message
     */
    public function seMuestraElMensaje(string $message): void
    {
        $this->assertContains($message);
    }

    private function executeSearch(string $term): void
    {
        $_GET['gymtrack_search'] = $term;

        if ('' !== $term) {
            delete_transient(
                'gymtrack_wger_' . md5(strtolower($term))
            );
        }

        $this->html = gymtrack_frontend_library_shortcode();
    }

    private function assertContains(string $expected): void
    {
        if (false === strpos($this->html, $expected)) {
            throw new \RuntimeException(
                'No se encontró el texto esperado: ' . $expected
            );
        }
    }
}

