<?php
/**
 * Plugin Name: GymTrack
 * Plugin URI: http://localhost:8082
 * Description: Plataforma de seguimiento de entrenamientos para una comunidad deportiva.
 * Version: 1.0.0
 * Author: Proyecto ASIR
 * License: GPL-2.0-or-later
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}


/**
 * =========================================================
 * CONTROL DE USUARIOS Y PERMISOS
 * =========================================================
 */

/*
 * Añade la capacidad gymtrack_use a los roles de WordPress.
 *
 * Esta capacidad permite:
 * - registrar entrenamientos
 * - consultar sus propios datos
 * - consultar su progreso
 *
 * No permite:
 * - gestionar ejercicios
 * - gestionar rutinas
 * - administrar el plugin
 */
function gymtrack_setup_roles() {

    $roles = array(
        'subscriber',
        'contributor',
        'author',
        'editor',
        'administrator',
    );

    foreach ( $roles as $role_name ) {

        $role = get_role( $role_name );

        if ( $role ) {
            $role->add_cap( 'gymtrack_use' );
        }
    }
}

add_action( 'init', 'gymtrack_setup_roles' );


/*
 * Comprobar si el usuario puede administrar GymTrack.
 */
function gymtrack_can_manage() {

    return current_user_can( 'manage_options' );
}


/*
 * Comprobar si el usuario puede utilizar GymTrack.
 */
function gymtrack_can_use() {

    return current_user_can( 'gymtrack_use' );
}


/*
 * Comprobar si el usuario actual es administrador.
 */
function gymtrack_is_admin() {

    return current_user_can( 'manage_options' );
}


/**
 * =========================================================
 * INSTALACIÓN DE LA BASE DE DATOS
 * =========================================================
 */

function gymtrack_activate() {

    global $wpdb;

    $charset_collate = $wpdb->get_charset_collate();

    require_once ABSPATH . 'wp-admin/includes/upgrade.php';

    $table_exercises = $wpdb->prefix . 'gymtrack_exercises';
    $table_workouts  = $wpdb->prefix . 'gymtrack_workouts';
    $table_sets      = $wpdb->prefix . 'gymtrack_sets';
    $table_routines  = $wpdb->prefix . 'gymtrack_routines';
    $table_routine_exercises = $wpdb->prefix . 'gymtrack_routine_exercises';


    /*
     * Tabla de ejercicios
     */

    $sql_exercises = "CREATE TABLE $table_exercises (
        id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
        name VARCHAR(100) NOT NULL,
        muscle_group VARCHAR(100) NOT NULL,
        description TEXT,
        PRIMARY KEY (id)
    ) $charset_collate;";


    /*
     * Tabla de entrenamientos
     */

    $sql_workouts = "CREATE TABLE $table_workouts (
        id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
        user_id BIGINT UNSIGNED NOT NULL,
        workout_date DATE NOT NULL,
        notes TEXT,
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (id),
        KEY user_id (user_id)
    ) $charset_collate;";


    /*
     * Tabla de series
     */

    $sql_sets = "CREATE TABLE $table_sets (
        id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
        workout_id BIGINT UNSIGNED NOT NULL,
        exercise_id BIGINT UNSIGNED NOT NULL,
        weight DECIMAL(6,2) NOT NULL DEFAULT 0,
        sets INT UNSIGNED NOT NULL DEFAULT 1,
        repetitions INT UNSIGNED NOT NULL DEFAULT 1,
        notes TEXT,
        PRIMARY KEY (id),
        KEY workout_id (workout_id),
        KEY exercise_id (exercise_id)
    ) $charset_collate;";


    /*
     * Tabla de rutinas
     */

    $sql_routines = "CREATE TABLE $table_routines (
        id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
        name VARCHAR(100) NOT NULL,
        description TEXT NULL,
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (id)
    ) $charset_collate;";


    /*
     * Tabla de ejercicios de las rutinas
     */

    $sql_routine_exercises = "CREATE TABLE $table_routine_exercises (
        id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
        routine_id BIGINT UNSIGNED NOT NULL,
        exercise_id BIGINT UNSIGNED NOT NULL,
        exercise_order INT UNSIGNED NOT NULL DEFAULT 1,
        target_sets INT UNSIGNED NOT NULL DEFAULT 3,
        target_repetitions INT UNSIGNED NOT NULL DEFAULT 10,
        rest_seconds INT UNSIGNED NOT NULL DEFAULT 90,
        notes TEXT NULL,
        PRIMARY KEY (id),
        KEY routine_id (routine_id),
        KEY exercise_id (exercise_id)
    ) $charset_collate;";


    dbDelta( $sql_exercises );
    dbDelta( $sql_workouts );
    dbDelta( $sql_sets );
    dbDelta( $sql_routines );
    dbDelta( $sql_routine_exercises );
}

register_activation_hook( __FILE__, 'gymtrack_activate' );


/**
 * =========================================================
 * MENÚ DE ADMINISTRACIÓN
 * =========================================================
 */

function gymtrack_admin_menu() {

    /*
     * El menú principal ahora requiere gymtrack_use.
     *
     * Así los usuarios normales pueden utilizar GymTrack.
     */
    add_menu_page(
        'GymTrack',
        'GymTrack',
        'gymtrack_use',
        'gymtrack',
        'gymtrack_workouts_page',
        'dashicons-heart',
        25
    );


    /*
     * Solo administradores.
     */

    add_submenu_page(
        'gymtrack',
        'Ejercicios',
        'Ejercicios',
        'manage_options',
        'gymtrack',
        'gymtrack_exercises_page'
    );


    /*
     * Administradores y usuarios normales.
     */

    add_submenu_page(
        'gymtrack',
        'Entrenamientos',
        'Entrenamientos',
        'gymtrack_use',
        'gymtrack-workouts',
        'gymtrack_workouts_page'
    );


    add_submenu_page(
        'gymtrack',
        'Historial',
        'Historial',
        'gymtrack_use',
        'gymtrack-history',
        'gymtrack_history_page'
    );


    add_submenu_page(
        'gymtrack',
        'Progreso',
        'Progreso',
        'gymtrack_use',
        'gymtrack-progress',
        'gymtrack_progress_page'
    );


    /*
     * Integración con una API externa de ejercicios.
     * Disponible para cualquier usuario de GymTrack.
     */

    add_submenu_page(
        'gymtrack',
        'Biblioteca externa',
        'Biblioteca externa',
        'gymtrack_use',
        'gymtrack-external-api',
        'gymtrack_external_api_page'
    );


    /*
     * Solo administradores.
     */

    add_submenu_page(
        'gymtrack',
        'Rutinas',
        'Rutinas',
        'manage_options',
        'gymtrack-routines',
        'gymtrack_routines_page'
    );


    /*
     * Herramienta de comprobación de la API REST.
     * Disponible para cualquier usuario que pueda utilizar GymTrack.
     */

    add_submenu_page(
        'gymtrack',
        'Prueba API REST',
        'Prueba API REST',
        'gymtrack_use',
        'gymtrack-rest-test',
        'gymtrack_rest_test_page'
    );
}

add_action( 'admin_menu', 'gymtrack_admin_menu' );


/**
 * =========================================================
 * PRUEBA DE LA API REST
 * =========================================================
 *
 * Esta página permite comprobar desde WordPress que la API REST
 * de GymTrack funciona con un usuario autenticado y con la
 * capacidad gymtrack_use. Se utiliza el mecanismo estándar de
 * autenticación REST mediante cookie + nonce wp_rest.
 */

function gymtrack_rest_test_page() {

    if ( ! gymtrack_can_use() ) {
        wp_die( 'No tienes permisos para utilizar GymTrack.' );
    }

    $nonce = wp_create_nonce( 'wp_rest' );
    $rest_base = trailingslashit( rest_url( 'gymtrack/v1' ) );

    $endpoints = array(
        'exercises' => array(
            'label' => 'Exercises',
            'url'   => $rest_base . 'exercises',
        ),
        'routines' => array(
            'label' => 'Routines',
            'url'   => $rest_base . 'routines',
        ),
        'workouts' => array(
            'label' => 'Workouts',
            'url'   => $rest_base . 'workouts',
        ),
        'history' => array(
            'label' => 'History',
            'url'   => $rest_base . 'history',
        ),
        'progress' => array(
            'label' => 'Progress',
            'url'   => $rest_base . 'progress',
        ),
    );
    ?>

    <div class="wrap">
        <h1>Prueba API REST de GymTrack</h1>

        <p>
            Esta herramienta comprueba los endpoints REST de GymTrack utilizando
            la sesión actual de WordPress y un nonce REST válido.
        </p>

        <p>
            <strong>Usuario:</strong>
            <?php echo esc_html( wp_get_current_user()->user_login ); ?>
            &nbsp;|&nbsp;
            <strong>Capacidad gymtrack_use:</strong> Sí
        </p>

        <p>
            <button type="button" class="button button-primary" id="gymtrack-run-rest-tests">
                Ejecutar todas las pruebas
            </button>
        </p>

        <div id="gymtrack-rest-test-results" style="margin-top:20px;"></div>
    </div>

    <script>
    (function () {
        const nonce = <?php echo wp_json_encode( $nonce ); ?>;
        const endpoints = <?php echo wp_json_encode( $endpoints ); ?>;
        const resultsContainer = document.getElementById('gymtrack-rest-test-results');
        const runButton = document.getElementById('gymtrack-run-rest-tests');

        function escapeHtml(value) {
            return String(value)
                .replace(/&/g, '&amp;')
                .replace(/</g, '&lt;')
                .replace(/>/g, '&gt;')
                .replace(/"/g, '&quot;')
                .replace(/'/g, '&#039;');
        }

        function renderResult(label, url, status, body, ok) {
            const wrapper = document.createElement('div');
            wrapper.style.border = '1px solid #ccd0d4';
            wrapper.style.background = '#fff';
            wrapper.style.padding = '15px';
            wrapper.style.marginBottom = '12px';
            wrapper.style.borderLeftWidth = '4px';
            wrapper.style.borderLeftColor = ok ? '#00a32a' : '#d63638';

            wrapper.innerHTML =
                '<h2 style="margin-top:0;">' + escapeHtml(label) + '</h2>' +
                '<p><strong>Endpoint:</strong> ' + escapeHtml(url) + '</p>' +
                '<p><strong>HTTP:</strong> ' + escapeHtml(status) + '</p>' +
                '<pre style="max-height:300px;overflow:auto;background:#f6f7f7;padding:10px;white-space:pre-wrap;">' +
                    escapeHtml(body) +
                '</pre>';

            resultsContainer.appendChild(wrapper);
        }

        async function testEndpoint(key, config) {
            try {
                const response = await fetch(config.url, {
                    method: 'GET',
                    credentials: 'same-origin',
                    headers: {
                        'X-WP-Nonce': nonce,
                        'Accept': 'application/json'
                    }
                });

                const body = await response.text();
                const ok = response.ok;

                renderResult(
                    config.label,
                    config.url,
                    response.status + ' ' + response.statusText,
                    body,
                    ok
                );

                return ok;
            } catch (error) {
                renderResult(
                    config.label,
                    config.url,
                    'ERROR',
                    error && error.message ? error.message : String(error),
                    false
                );

                return false;
            }
        }

        runButton.addEventListener('click', async function () {
            runButton.disabled = true;
            runButton.textContent = 'Ejecutando...';
            resultsContainer.innerHTML = '';

            let passed = 0;
            let failed = 0;

            for (const [key, config] of Object.entries(endpoints)) {
                const ok = await testEndpoint(key, config);
                if (ok) {
                    passed++;
                } else {
                    failed++;
                }
            }

            const summary = document.createElement('div');
            summary.style.padding = '15px';
            summary.style.marginTop = '20px';
            summary.style.background = failed === 0 ? '#edfaef' : '#fcf0f1';
            summary.style.border = '1px solid ' + (failed === 0 ? '#00a32a' : '#d63638');
            summary.innerHTML =
                '<strong>Resultado:</strong> ' +
                passed + ' pruebas correctas, ' +
                failed + ' pruebas fallidas.';

            resultsContainer.appendChild(summary);

            runButton.disabled = false;
            runButton.textContent = 'Ejecutar todas las pruebas';
        });
    }());
    </script>
    <?php
}


/**
 * =========================================================
 * LIMPIEZA DE LA NAVEGACIÓN PÚBLICA
 * =========================================================
 * Elimina la antigua página de ejemplo de WordPress de la navegación
 * pública, tanto en menús clásicos como en navegación basada en bloques.
 * Se mantienen las páginas funcionales de GymTrack.
 */

function gymtrack_remove_default_example_page_from_menu( $items, $args ) {

    foreach ( $items as $key => $item ) {
        $url   = isset( $item->url ) ? (string) $item->url : '';
        $title = isset( $item->title ) ? trim( wp_strip_all_tags( $item->title ) ) : '';

        if (
            false !== strpos( $url, 'page_id=2' ) ||
            false !== strpos( $url, '/pagina-de-ejemplo' ) ||
            false !== strpos( $url, '/sample-page' ) ||
            'Página de ejemplo' === $title
        ) {
            unset( $items[ $key ] );
        }
    }

    return $items;
}

add_filter(
    'wp_nav_menu_objects',
    'gymtrack_remove_default_example_page_from_menu',
    20,
    2
);

/**
 * Twenty Twenty-Five puede utilizar core/navigation-link. En ese caso el
 * menú no pasa por wp_nav_menu_objects, por lo que filtramos el bloque
 * directamente antes de renderizarlo.
 */
function gymtrack_remove_default_example_page_from_block_navigation( $block_content, $block ) {

    if (
        empty( $block['blockName'] ) ||
        'core/navigation-link' !== $block['blockName']
    ) {
        return $block_content;
    }

    $attrs = isset( $block['attrs'] ) && is_array( $block['attrs'] )
        ? $block['attrs']
        : array();

    $label = isset( $attrs['label'] )
        ? trim( wp_strip_all_tags( $attrs['label'] ) )
        : '';

    $url = isset( $attrs['url'] ) ? (string) $attrs['url'] : '';

    if (
        'Página de ejemplo' === $label ||
        false !== strpos( $url, 'page_id=2' ) ||
        false !== strpos( $url, '/pagina-de-ejemplo' ) ||
        false !== strpos( $url, '/sample-page' )
    ) {
        return '';
    }

    return $block_content;
}

add_filter(
    'render_block_core/navigation-link',
    'gymtrack_remove_default_example_page_from_block_navigation',
    20,
    2
);

/**
 * =========================================================
 * FLUJO DE ACCESO DEL FRONT-END
 * =========================================================
 *
 * GymTrack funciona como una aplicacion web dentro de WordPress.
 *
 * - Usuario no autenticado en la portada: va al login de WordPress.
 * - Usuario autenticado en la portada: ve el dashboard de GymTrack.
 * - Paginas funcionales de GymTrack: requieren autenticacion.
 * - Tras iniciar sesion se conserva un redirect_to seguro cuando existe;
 *   en caso contrario se utiliza la portada de GymTrack.
 * - El area /wp-admin/ sigue disponible para los administradores.
 */
function gymtrack_frontend_auth_redirect() {

    if (
        is_admin() ||
        wp_doing_ajax() ||
        wp_doing_cron() ||
        ( function_exists( 'wp_doing_rest' ) && wp_doing_rest() )
    ) {
        return;
    }

    global $pagenow;

    if ( 'wp-login.php' === $pagenow ) {
        return;
    }

    $protected_slugs = array(
        'gymtrack',
        'gymtrack-registrar',
        'gymtrack-historial',
        'gymtrack-progreso',
        'gymtrack-biblioteca',
    );

    $is_gymtrack_page = false;

    foreach ( $protected_slugs as $slug ) {
        if ( is_page( $slug ) ) {
            $is_gymtrack_page = true;
            break;
        }
    }

    if ( ! is_front_page() && ! $is_gymtrack_page ) {
        return;
    }

    if ( ! is_user_logged_in() ) {
        $request_uri = isset( $_SERVER['REQUEST_URI'] )
            ? wp_unslash( $_SERVER['REQUEST_URI'] )
            : '/';

        $redirect_to = home_url( $request_uri );
        $login_url   = wp_login_url( $redirect_to );

        wp_safe_redirect( $login_url );
        exit;
    }

    if ( ! gymtrack_can_use() ) {
        wp_safe_redirect( home_url( '/' ) );
        exit;
    }
}

add_action( 'template_redirect', 'gymtrack_frontend_auth_redirect', 1 );


/**
 * Tras un login correcto, GymTrack utiliza la portada de la aplicacion
 * como destino por defecto. Si WordPress ya proporciono un redirect_to
 * seguro (por ejemplo, desde /wp-admin/), se respeta ese destino.
 */
function gymtrack_login_redirect( $redirect_to, $requested_redirect_to, $user ) {

    if ( is_wp_error( $user ) || ! ( $user instanceof WP_User ) ) {
        return $redirect_to;
    }

    if ( ! empty( $requested_redirect_to ) ) {
        $safe_redirect = wp_validate_redirect( $requested_redirect_to, '' );

        if ( '' !== $safe_redirect ) {
            return $safe_redirect;
        }
    }

    return home_url( '/' );
}

add_filter( 'login_redirect', 'gymtrack_login_redirect', 10, 3 );


/**
 * =========================================================
 * INTERFAZ FRONT-END DE GYMTRACK
 * =========================================================
 */

function gymtrack_frontend_dashboard_shortcode() {

    if ( ! is_user_logged_in() || ! gymtrack_can_use() ) {
        return '<div class="gymtrack-notice"><p>Debes iniciar sesión para utilizar GymTrack.</p></div>';
    }

    global $wpdb;

    $user_id = get_current_user_id();
    $table_workouts = $wpdb->prefix . 'gymtrack_workouts';
    $table_sets = $wpdb->prefix . 'gymtrack_sets';
    $table_exercises = $wpdb->prefix . 'gymtrack_exercises';

    $recent_workouts = $wpdb->get_results(
        $wpdb->prepare(
            "SELECT
                w.id,
                w.workout_date,
                e.name AS exercise_name,
                s.weight,
                s.sets,
                s.repetitions
            FROM $table_workouts w
            INNER JOIN $table_sets s ON w.id = s.workout_id
            INNER JOIN $table_exercises e ON s.exercise_id = e.id
            WHERE w.user_id = %d
            AND s.weight > 0
            ORDER BY w.workout_date DESC, w.id DESC, s.id ASC
            LIMIT 5",
            $user_id
        )
    );

    /*
     * La consulta anterior necesita el peso máximo de cada ejercicio para
     * localizar la fecha. Se recalcula de forma segura en PHP para evitar
     * depender de una subconsulta con MAX().
     */
    $best_marks = $wpdb->get_results(
        $wpdb->prepare(
            "SELECT
                e.name AS exercise_name,
                s.weight,
                w.workout_date
            FROM $table_workouts w
            INNER JOIN $table_sets s ON w.id = s.workout_id
            INNER JOIN $table_exercises e ON s.exercise_id = e.id
            WHERE w.user_id = %d
            AND s.weight > 0
            ORDER BY s.exercise_id ASC, s.weight DESC, w.workout_date DESC, w.id DESC",
            $user_id
        )
    );

    $best_by_exercise = array();

    foreach ( $best_marks as $mark ) {
        if ( ! isset( $best_by_exercise[ $mark->exercise_name ] ) ) {
            $best_by_exercise[ $mark->exercise_name ] = $mark;
        }
    }

    $best_marks = array_slice( array_values( $best_by_exercise ), 0, 5 );

    $total_workouts = (int) $wpdb->get_var(
        $wpdb->prepare(
            "SELECT COUNT(DISTINCT w.id)
            FROM $table_workouts w
            INNER JOIN $table_sets s ON w.id = s.workout_id
            WHERE w.user_id = %d
            AND s.weight > 0",
            $user_id
        )
    );

    $total_exercises = (int) $wpdb->get_var(
        $wpdb->prepare(
            "SELECT COUNT(DISTINCT s.exercise_id)
            FROM $table_workouts w
            INNER JOIN $table_sets s ON w.id = s.workout_id
            WHERE w.user_id = %d
            AND s.weight > 0",
            $user_id
        )
    );

    ob_start();
    ?>

    <?php
    $gymtrack_home_url       = home_url( '/' );
    $gymtrack_register_url   = gymtrack_frontend_page_url( 'gymtrack-registrar' );
    $gymtrack_history_url    = gymtrack_frontend_page_url( 'gymtrack-historial' );
    $gymtrack_progress_url   = gymtrack_frontend_page_url( 'gymtrack-progreso' );
    $gymtrack_library_url    = gymtrack_frontend_page_url( 'gymtrack-biblioteca' );
    $gymtrack_logout_url     = wp_logout_url( $gymtrack_home_url );
    $gymtrack_user_name      = wp_get_current_user()->display_name ?: wp_get_current_user()->user_login;
    $gymtrack_background_url = plugins_url( 'assets/gymtrack-home.jpg', __FILE__ );
    $gymtrack_registra_image_url  = gymtrack_frontend_asset_url( 'assets/gymtrack-registra.jpg' );
    $gymtrack_analiza_image_url   = gymtrack_frontend_asset_url( 'assets/gymtrack-analiza.jpg' );
    $gymtrack_superate_image_url  = gymtrack_frontend_asset_url( 'assets/gymtrack-superate.jpg' );
    $gymtrack_buscar_image_url    = gymtrack_frontend_asset_url( 'assets/gymtrack-buscar.jpg' );
    ?>
    <style>
        /* =====================================================
         * GYMTRACK — PORTADA VISUAL
         * ===================================================== */
        body.gymtrack-home-page {
            background: #07090b !important;
            color: #f5f5f5;
        }

        body.gymtrack-home-page header.wp-block-template-part,
        body.gymtrack-home-page .wp-site-blocks > header,
        body.gymtrack-home-page .entry-header,
        body.gymtrack-home-page .wp-block-post-title,
        body.gymtrack-home-page .entry-title,
        body.gymtrack-home-page > footer,
        body.gymtrack-home-page .wp-site-blocks > footer {
            display: none !important;
        }

        body.gymtrack-home-page .wp-site-blocks,
        body.gymtrack-home-page .site-content,
        body.gymtrack-home-page main,
        body.gymtrack-home-page .entry-content {
            margin: 0 !important;
            padding: 0 !important;
            max-width: none !important;
        }

        body.gymtrack-home-page .gymtrack-dashboard {
            width: 100%;
            max-width: 100%;
            max-width: none;
            margin: 0;
            padding: 0;
            font-family: Inter, ui-sans-serif, -apple-system, BlinkMacSystemFont, "Segoe UI", Arial, sans-serif;
            color: #f6f7f8;
            box-sizing: border-box;
            overflow: hidden;
            background: #07090b;
        }

        body.gymtrack-home-page .gymtrack-dashboard *,
        body.gymtrack-home-page .gymtrack-dashboard *::before,
        body.gymtrack-home-page .gymtrack-dashboard *::after {
            box-sizing: border-box;
        }

        .gymtrack-home-hero {
            position: relative;
            min-height: calc(100vh - 32px);
            display: flex;
            flex-direction: column;
            justify-content: space-between;
            isolation: isolate;
            background:
                linear-gradient(90deg, rgba(3,5,7,.94) 0%, rgba(3,5,7,.78) 34%, rgba(3,5,7,.28) 68%, rgba(3,5,7,.44) 100%),
                linear-gradient(180deg, rgba(0,0,0,.50) 0%, rgba(0,0,0,.05) 36%, rgba(0,0,0,.80) 100%),
                url('<?php echo esc_url( $gymtrack_background_url ); ?>') center center / cover no-repeat;
        }

        .gymtrack-home-hero::after {
            content: "";
            position: absolute;
            inset: 0;
            z-index: -1;
            pointer-events: none;
            background:
                radial-gradient(circle at 58% 48%, rgba(255, 126, 0, .10), transparent 32%),
                linear-gradient(90deg, rgba(255,126,0,.07), transparent 25%);
        }

        .gymtrack-home-nav {
            position: relative;
            z-index: 5;
            width: min(1380px, calc(100% - 64px));
            margin: 24px auto 0;
            padding: 14px 20px 14px 26px;
            min-height: 70px;
            display: flex;
            align-items: center;
            gap: 30px;
            border: 1px solid rgba(255,255,255,.09);
            border-radius: 22px;
            background: rgba(7,9,11,.84);
            backdrop-filter: blur(16px);
            box-shadow: 0 18px 55px rgba(0,0,0,.34);
        }

        .gymtrack-home-logo {
            display: inline-flex;
            align-items: center;
            gap: 11px;
            margin-right: auto;
            color: #fff !important;
            text-decoration: none !important;
            font-weight: 900;
            font-size: 27px;
            line-height: 1;
            letter-spacing: -1.4px;
            white-space: nowrap;
        }

        .gymtrack-home-logo-mark {
            width: 35px;
            height: 35px;
            display: grid;
            place-items: center;
            color: #ff8614;
            filter: drop-shadow(0 0 9px rgba(255,126,0,.35));
        }

        .gymtrack-home-logo-mark svg {
            width: 100%;
            height: 100%;
            fill: currentColor;
        }

        .gymtrack-home-logo-accent {
            color: #ff8614;
        }

        .gymtrack-home-links {
            display: flex;
            align-items: center;
            gap: 30px;
        }

        .gymtrack-home-links a {
            position: relative;
            padding: 13px 0;
            color: rgba(255,255,255,.82) !important;
            text-decoration: none !important;
            font-size: 15px;
            font-weight: 600;
            transition: color .2s ease;
        }

        .gymtrack-home-links a:hover,
        .gymtrack-home-links a.gymtrack-home-active {
            color: #fff !important;
        }

        .gymtrack-home-links a.gymtrack-home-active::after {
            content: "";
            position: absolute;
            left: 0;
            right: 0;
            bottom: 3px;
            height: 3px;
            border-radius: 99px;
            background: #ff8614;
            box-shadow: 0 0 10px rgba(255,126,0,.55);
        }

        .gymtrack-home-user {
            display: flex;
            align-items: center;
            gap: 13px;
            margin-left: 5px;
        }

        .gymtrack-home-user a {
            color: #fff !important;
            text-decoration: none !important;
        }

        .gymtrack-home-user-icon,
        .gymtrack-home-logout-icon {
            width: 28px;
            height: 28px;
            display: grid;
            place-items: center;
        }

        .gymtrack-home-user-icon svg,
        .gymtrack-home-logout-icon svg {
            width: 24px;
            height: 24px;
            fill: none;
            stroke: currentColor;
            stroke-width: 1.8;
            stroke-linecap: round;
            stroke-linejoin: round;
        }

        .gymtrack-home-logout-icon {
            color: #ff8614;
        }

        .gymtrack-home-content {
            position: relative;
            z-index: 2;
            width: min(1380px, calc(100% - 64px));
            margin: 0 auto;
            padding: clamp(55px, 8vh, 105px) 0 38px;
        }

        .gymtrack-home-kicker {
            margin: 0 0 8px;
            color: rgba(255,255,255,.92);
            font-size: clamp(13px, 1.1vw, 17px);
            font-weight: 500;
            letter-spacing: .30em;
            text-transform: uppercase;
        }

        .gymtrack-home-title {
            margin: 0 !important;
            font-family: Impact, Haettenschweiler, "Arial Black", sans-serif !important;
            font-size: clamp(74px, 10vw, 152px) !important;
            line-height: .82 !important;
            letter-spacing: -.055em !important;
            text-transform: uppercase;
            color: #fff !important;
            text-shadow: 0 10px 38px rgba(0,0,0,.38);
        }

        .gymtrack-home-title span {
            color: #ff8614;
        }

        .gymtrack-home-tagline {
            margin: 18px 0 0 !important;
            color: #fff !important;
            font-size: clamp(23px, 2.2vw, 38px) !important;
            line-height: 1 !important;
            font-weight: 800 !important;
            letter-spacing: .18em;
            text-transform: uppercase;
        }

        .gymtrack-home-tagline span {
            color: #ff8614;
        }

        .gymtrack-home-description {
            max-width: 650px;
            margin: 25px 0 0 !important;
            color: rgba(255,255,255,.78) !important;
            font-size: 17px;
            line-height: 1.65;
        }

        .gymtrack-home-actions {
            display: flex;
            flex-wrap: wrap;
            gap: 15px;
            margin-top: 30px;
        }

        .gymtrack-home-cta {
            position: relative;
            min-width: 205px;
            min-height: 62px;
            display: inline-flex;
            align-items: center;
            justify-content: flex-start;
            gap: 11px;
            padding: 9px 15px;
            border-radius: 17px;
            overflow: hidden;
            isolation: isolate;
            text-decoration: none !important;
            font-size: 14px;
            font-weight: 850;
            letter-spacing: .04em;
            text-transform: uppercase;
            transition: transform .2s ease, box-shadow .2s ease, background .2s ease;
            background-position: center;
            background-size: cover;
            background-repeat: no-repeat;
        }

        .gymtrack-home-cta::before {
            content: "";
            position: absolute;
            inset: 0;
            z-index: -1;
            background: linear-gradient(90deg, rgba(5,7,9,.94) 0%, rgba(5,7,9,.72) 46%, rgba(5,7,9,.35) 100%);
            pointer-events: none;
        }

        .gymtrack-home-cta-register {
            background-image: url('<?php echo esc_url( $gymtrack_registra_image_url ); ?>');
        }

        .gymtrack-home-cta-history {
            background-image: url('<?php echo esc_url( $gymtrack_analiza_image_url ); ?>');
        }

        .gymtrack-home-cta-progress {
            background-image: url('<?php echo esc_url( $gymtrack_superate_image_url ); ?>');
        }

        .gymtrack-home-cta-library {
            background-image: url('<?php echo esc_url( $gymtrack_buscar_image_url ); ?>');
        }

        .gymtrack-home-cta > * {
            position: relative;
            z-index: 1;
        }

        .gymtrack-home-cta-thumb {
            display: block !important;
            width: 46px;
            height: 44px;
            flex: 0 0 46px;
            object-fit: cover;
            border-radius: 9px;
            border: 1px solid rgba(255,255,255,.24);
            box-shadow: 0 5px 14px rgba(0,0,0,.28);
        }

        .gymtrack-home-cta-label {
            flex: 1 1 auto;
            text-align: left;
            white-space: nowrap;
        }

        .gymtrack-home-cta .gymtrack-home-arrow {
            flex: 0 0 auto;
            margin-left: auto;
        }

        .gymtrack-home-cta:hover {
            transform: translateY(-2px);
        }

        .gymtrack-home-cta-register {
            box-shadow: 0 12px 32px rgba(255,115,0,.28);
        }

        .gymtrack-home-cta-register::before {
            background: linear-gradient(90deg, rgba(255,126,0,.92) 0%, rgba(255,111,0,.66) 48%, rgba(12,14,16,.30) 100%);
        }

        .gymtrack-home-cta-register:hover {
            color: #fff !important;
            box-shadow: 0 16px 38px rgba(255,115,0,.40);
        }

        .gymtrack-home-cta-history,
        .gymtrack-home-cta-progress,
        .gymtrack-home-cta-library {
            border: 1px solid rgba(255,255,255,.38);
            background-color: rgba(10,12,14,.25);
            backdrop-filter: blur(7px);
        }

        .gymtrack-home-cta-history:hover,
        .gymtrack-home-cta-progress:hover,
        .gymtrack-home-cta-library:hover {
            color: #fff !important;
            background-color: rgba(255,255,255,.10);
        }

        .gymtrack-home-arrow {
            font-size: 22px;
            line-height: 1;
        }

        .gymtrack-home-motto {
            position: absolute;
            right: 0;
            top: 41%;
            width: 230px;
            padding-left: 22px;
            border-left: 3px solid #ff8614;
            color: rgba(255,255,255,.85);
            font-size: 18px;
            line-height: 1.25;
            font-weight: 800;
            text-transform: uppercase;
            letter-spacing: .02em;
        }

        .gymtrack-home-motto strong {
            display: block;
            margin-top: 10px;
            color: #ff8614;
            font-size: 10px;
            letter-spacing: .25em;
        }

        /* =====================================================
         * DATOS DEL USUARIO — SEGUNDA SECCIÓN
         * ===================================================== */
        .gymtrack-home-data {
            width: min(1180px, calc(100% - 48px));
            margin: 0 auto;
            padding: 70px 0 90px;
        }

        .gymtrack-home-section-heading {
            margin-bottom: 28px;
        }

        .gymtrack-home-section-kicker {
            margin: 0 0 7px !important;
            color: #ff8614 !important;
            font-size: 12px !important;
            font-weight: 800 !important;
            letter-spacing: .22em;
            text-transform: uppercase;
        }

        .gymtrack-home-section-title {
            margin: 0 !important;
            color: #fff !important;
            font-size: clamp(30px, 4vw, 46px) !important;
            line-height: 1 !important;
            font-weight: 850 !important;
        }

        .gymtrack-home-welcome {
            margin: 9px 0 0 !important;
            color: rgba(255,255,255,.58) !important;
        }

        .gymtrack-home-stat-grid {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 18px;
            margin-bottom: 18px;
        }

        .gymtrack-home-stat-card,
        .gymtrack-home-data-card {
            border: 1px solid rgba(255,255,255,.09);
            border-radius: 20px;
            background: linear-gradient(145deg, rgba(24,27,30,.96), rgba(13,15,17,.96));
            box-shadow: 0 15px 45px rgba(0,0,0,.20);
        }

        .gymtrack-home-stat-card {
            padding: 25px 28px;
        }

        .gymtrack-home-stat-label {
            margin: 0 !important;
            color: rgba(255,255,255,.58) !important;
            font-size: 13px !important;
            text-transform: uppercase;
            letter-spacing: .08em;
        }

        .gymtrack-home-stat {
            margin: 7px 0 0 !important;
            color: #ff8614 !important;
            font-size: 42px !important;
            line-height: 1 !important;
            font-weight: 900 !important;
        }

        .gymtrack-home-data-card {
            margin-bottom: 18px;
            padding: 27px;
            overflow: hidden;
        }

        .gymtrack-home-data-card h2 {
            margin: 0 0 20px !important;
            color: #fff !important;
            font-size: 22px !important;
        }

        .gymtrack-home-data-card table {
            width: 100%;
            border-collapse: collapse;
            color: rgba(255,255,255,.82);
        }

        .gymtrack-home-data-card th,
        .gymtrack-home-data-card td {
            padding: 13px 10px;
            border-bottom: 1px solid rgba(255,255,255,.08);
            text-align: left;
        }

        .gymtrack-home-data-card th {
            color: #ff8614;
            font-size: 11px;
            text-transform: uppercase;
            letter-spacing: .08em;
        }

        .gymtrack-home-data-card td {
            font-size: 14px;
        }

        .gymtrack-home-empty {
            color: rgba(255,255,255,.56) !important;
        }

        .gymtrack-home-quick-actions {
            display: flex;
            flex-wrap: wrap;
            gap: 11px;
        }

        .gymtrack-home-quick-actions a {
            display: inline-flex;
            align-items: center;
            min-height: 45px;
            padding: 10px 16px;
            border: 1px solid rgba(255,255,255,.14);
            border-radius: 12px;
            color: #fff !important;
            background: rgba(255,255,255,.04);
            text-decoration: none !important;
            font-size: 13px;
            font-weight: 700;
        }

        .gymtrack-home-quick-actions a:hover {
            border-color: rgba(255,134,20,.65);
            color: #ff9a28 !important;
        }

        @media (max-width: 1050px) {
            .gymtrack-home-links {
                gap: 18px;
            }

            .gymtrack-home-links a {
                font-size: 13px;
            }

            .gymtrack-home-motto {
                display: none;
            }

        }

        @media (max-width: 760px) {
            .gymtrack-home-nav,
            .gymtrack-home-content {
                width: min(100% - 28px, 1380px);
            }

            .gymtrack-home-nav {
                margin-top: 14px;
                padding: 12px 15px;
                border-radius: 17px;
            }

            .gymtrack-home-logo {
                font-size: 22px;
            }

            .gymtrack-home-links {
                display: none;
            }

            .gymtrack-home-content {
                padding-top: 65px;
            }

            .gymtrack-home-title {
                font-size: clamp(63px, 19vw, 112px) !important;
            }

            .gymtrack-home-tagline {
                font-size: 20px !important;
                letter-spacing: .08em;
            }

            .gymtrack-home-description {
                font-size: 15px;
            }

            .gymtrack-home-actions {
                flex-direction: column;
            }

            .gymtrack-home-cta {
                width: 100%;
                min-height: 58px;
            }

            .gymtrack-home-cta-thumb {
                width: 42px;
                height: 40px;
                flex-basis: 42px;
            }

            .gymtrack-home-stat-grid {
                grid-template-columns: 1fr;
            }

            .gymtrack-home-data {
                width: calc(100% - 28px);
                padding-top: 50px;
            }

            .gymtrack-home-data-card {
                overflow-x: auto;
            }

            .gymtrack-home-data-card table {
                min-width: 620px;
            }
        }
    </style>

    <div class="gymtrack-dashboard">
        <section class="gymtrack-home-hero">
            <nav class="gymtrack-home-nav" aria-label="Navegación principal">
                <a class="gymtrack-home-logo" href="<?php echo esc_url( $gymtrack_home_url ); ?>">
                    <span class="gymtrack-home-logo-mark" aria-hidden="true">
                        <svg viewBox="0 0 64 64">
                            <path d="M12 24h8v16h-8c-2.2 0-4-1.8-4-4V28c0-2.2 1.8-4 4-4Zm32 0h8c2.2 0 4 1.8 4 4v8c0 2.2-1.8 4-4 4h-8V24ZM23 28h18v8H23v-8Zm-9-12h6v8h-6v-8Zm31 0h6v8h-6v-8Zm-31 32h6v8h-6v-8Zm31 0h6v8h-6v-8Z"/>
                        </svg>
                    </span>
                    <span>GYM<span class="gymtrack-home-logo-accent">TRACK</span></span>
                </a>

                <div class="gymtrack-home-links">
                    <a class="gymtrack-home-active" href="<?php echo esc_url( $gymtrack_home_url ); ?>">Inicio</a>
                    <a href="<?php echo esc_url( $gymtrack_register_url ); ?>">Entrenamientos</a>
                    <a href="<?php echo esc_url( $gymtrack_history_url ); ?>">Historial</a>
                    <a href="<?php echo esc_url( $gymtrack_progress_url ); ?>">Progreso</a>
                    <a href="<?php echo esc_url( $gymtrack_library_url ); ?>">Biblioteca</a>
                </div>

                <div class="gymtrack-home-user">
                    <span class="gymtrack-home-user-icon" title="<?php echo esc_attr( $gymtrack_user_name ); ?>" aria-label="<?php echo esc_attr( $gymtrack_user_name ); ?>">
                        <svg viewBox="0 0 24 24">
                            <circle cx="12" cy="8" r="3.2"></circle>
                            <path d="M5.5 20c.8-3.5 3-5.2 6.5-5.2s5.7 1.7 6.5 5.2"></path>
                        </svg>
                    </span>
                    <a class="gymtrack-home-logout-icon" href="<?php echo esc_url( $gymtrack_logout_url ); ?>" title="Cerrar sesión" aria-label="Cerrar sesión">
                        <svg viewBox="0 0 24 24">
                            <path d="M9 5H5.5A1.5 1.5 0 0 0 4 6.5v11A1.5 1.5 0 0 0 5.5 19H9"></path>
                            <path d="M14 8l4 4-4 4"></path>
                            <path d="M18 12H9"></path>
                        </svg>
                    </a>
                </div>
            </nav>

            <div class="gymtrack-home-content">
                <p class="gymtrack-home-kicker">Tu progreso, nuestro objetivo</p>
                <h1 class="gymtrack-home-title">GYM<span>TRACK</span></h1>
                <p class="gymtrack-home-tagline">Entrena<span>.</span> Registra<span>.</span> Supérate<span>.</span></p>
                <p class="gymtrack-home-description">
                    Lleva el control de tus entrenamientos, analiza tu progreso
                    y alcanza tu mejor versión.
                </p>

                <div class="gymtrack-home-actions">
                    <a class="gymtrack-home-cta gymtrack-home-cta-register" href="<?php echo esc_url( $gymtrack_register_url ); ?>">
                        <img class="gymtrack-home-cta-thumb" src="<?php echo esc_url( $gymtrack_registra_image_url ); ?>" alt="">
                        <span class="gymtrack-home-cta-label">Registra</span>
                        <span class="gymtrack-home-arrow" aria-hidden="true">›</span>
                    </a>
                    <a class="gymtrack-home-cta gymtrack-home-cta-history" href="<?php echo esc_url( $gymtrack_history_url ); ?>">
                        <img class="gymtrack-home-cta-thumb" src="<?php echo esc_url( $gymtrack_analiza_image_url ); ?>" alt="">
                        <span class="gymtrack-home-cta-label">Analiza</span>
                        <span class="gymtrack-home-arrow" aria-hidden="true">›</span>
                    </a>
                    <a class="gymtrack-home-cta gymtrack-home-cta-progress" href="<?php echo esc_url( $gymtrack_progress_url ); ?>">
                        <img class="gymtrack-home-cta-thumb" src="<?php echo esc_url( $gymtrack_superate_image_url ); ?>" alt="">
                        <span class="gymtrack-home-cta-label">Supérate</span>
                        <span class="gymtrack-home-arrow" aria-hidden="true">›</span>
                    </a>
                    <a class="gymtrack-home-cta gymtrack-home-cta-library" href="<?php echo esc_url( $gymtrack_library_url ); ?>">
                        <img class="gymtrack-home-cta-thumb" src="<?php echo esc_url( $gymtrack_buscar_image_url ); ?>" alt="">
                        <span class="gymtrack-home-cta-label">Buscar ejercicios</span>
                        <span class="gymtrack-home-arrow" aria-hidden="true">›</span>
                    </a>
                </div>

                <div class="gymtrack-home-motto">
                    Disciplina hoy<br>
                    resultados mañana
                    <strong>GymTrack</strong>
                </div>
            </div>

        </section>

        <section class="gymtrack-home-data">
            <div class="gymtrack-home-section-heading">
                <p class="gymtrack-home-section-kicker">Tu actividad</p>
                <h2 class="gymtrack-home-section-title">Tu progreso empieza aquí.</h2>
                <p class="gymtrack-home-welcome">
                    Bienvenido, <?php echo esc_html( $gymtrack_user_name ); ?>.
                </p>
            </div>

            <div class="gymtrack-home-stat-grid">
                <div class="gymtrack-home-stat-card">
                    <p class="gymtrack-home-stat-label">Entrenamientos registrados</p>
                    <p class="gymtrack-home-stat"><?php echo esc_html( $total_workouts ); ?></p>
                </div>
                <div class="gymtrack-home-stat-card">
                    <p class="gymtrack-home-stat-label">Ejercicios realizados</p>
                    <p class="gymtrack-home-stat"><?php echo esc_html( $total_exercises ); ?></p>
                </div>
            </div>

            <div class="gymtrack-home-data-card">
                <h2>Últimos entrenamientos</h2>
                <?php if ( empty( $recent_workouts ) ) : ?>
                    <p class="gymtrack-home-empty">Todavía no tienes entrenamientos registrados.</p>
                <?php else : ?>
                    <table>
                        <thead>
                            <tr>
                                <th>Fecha</th>
                                <th>Ejercicio</th>
                                <th>Peso</th>
                                <th>Series</th>
                                <th>Repeticiones</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ( $recent_workouts as $workout ) : ?>
                                <tr>
                                    <td><?php echo esc_html( $workout->workout_date ); ?></td>
                                    <td><?php echo esc_html( $workout->exercise_name ); ?></td>
                                    <td><?php echo esc_html( number_format_i18n( (float) $workout->weight, 2 ) ); ?> kg</td>
                                    <td><?php echo esc_html( $workout->sets ); ?></td>
                                    <td><?php echo esc_html( $workout->repetitions ); ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php endif; ?>
            </div>

            <div class="gymtrack-home-data-card">
                <h2>Mejores marcas</h2>
                <?php if ( empty( $best_marks ) ) : ?>
                    <p class="gymtrack-home-empty">Todavía no tienes marcas registradas.</p>
                <?php else : ?>
                    <table>
                        <thead>
                            <tr>
                                <th>Ejercicio</th>
                                <th>Mejor peso</th>
                                <th>Fecha</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ( $best_marks as $mark ) : ?>
                                <tr>
                                    <td><?php echo esc_html( $mark->exercise_name ); ?></td>
                                    <td><?php echo esc_html( number_format_i18n( (float) $mark->weight, 2 ) ); ?> kg</td>
                                    <td><?php echo esc_html( $mark->workout_date ); ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php endif; ?>
            </div>

            <div class="gymtrack-home-data-card">
                <h2>Acciones rápidas</h2>
                <div class="gymtrack-home-quick-actions">
                    <a href="<?php echo esc_url( $gymtrack_register_url ); ?>">Registrar entrenamiento</a>
                    <a href="<?php echo esc_url( $gymtrack_history_url ); ?>">Ver historial</a>
                    <a href="<?php echo esc_url( $gymtrack_progress_url ); ?>">Ver progreso</a>
                    <a href="<?php echo esc_url( $gymtrack_library_url ); ?>">Biblioteca de ejercicios</a>
                </div>
            </div>
        </section>
    </div>


    <?php
    return ob_get_clean();
}

add_shortcode( 'gymtrack_dashboard', 'gymtrack_frontend_dashboard_shortcode' );


function gymtrack_frontend_page_url( $slug ) {
    $page = get_page_by_path( $slug );
    return $page instanceof WP_Post ? get_permalink( $page->ID ) : home_url( '/' . trim( $slug, '/' ) . '/' );
}

function gymtrack_frontend_access_check() {
    if ( ! is_user_logged_in() || ! gymtrack_can_use() ) {
        return '<div class="gymtrack-frontend"><div class="gymtrack-message error"><p>Debes iniciar sesión para utilizar GymTrack.</p></div></div>';
    }
    return '';
}

function gymtrack_frontend_styles() {
    static $printed = false;
    if ( $printed ) { return ''; }
    $printed = true;
    return '<style>
        html,body{overflow-x:hidden}
        body.gymtrack-app-page{background:#050607 !important;color:#f4f5f6;overflow-x:hidden !important}
        body.gymtrack-app-page header.wp-block-template-part,
        body.gymtrack-app-page .wp-site-blocks > header,
        body.gymtrack-app-page .entry-header,
        body.gymtrack-app-page .wp-block-post-title,
        body.gymtrack-app-page .entry-title,
        body.gymtrack-app-page > footer,
        body.gymtrack-app-page .wp-site-blocks > footer{display:none !important}
        body.gymtrack-app-page .wp-site-blocks,
        body.gymtrack-app-page .site-content,
        body.gymtrack-app-page main,
        body.gymtrack-app-page .entry-content,
        body.gymtrack-app-page .wp-block-post-content{
            width:100% !important;
            max-width:none !important;
            margin:0 !important;
            padding:0 !important;
        }
        body.gymtrack-app-page main,
        body.gymtrack-app-page .entry-content,
        body.gymtrack-app-page .wp-block-post-content{
            min-width:0 !important;
        }
        body.gymtrack-app-page .wp-site-blocks > main{
            width:100vw !important;
            max-width:none !important;
            margin-left:calc(50% - 50vw) !important;
            margin-right:calc(50% - 50vw) !important;
        }
        body.gymtrack-app-page .entry-content{
            width:100vw !important;
            max-width:none !important;
            margin-left:calc(50% - 50vw) !important;
            margin-right:calc(50% - 50vw) !important;
        }
        .gymtrack-frontend{max-width:1180px;margin:32px auto 70px;padding:0 20px;font-family:Inter,ui-sans-serif,-apple-system,BlinkMacSystemFont,"Segoe UI",Arial,sans-serif;color:#f4f5f6}.gymtrack-frontend h1{margin-bottom:8px}.gymtrack-frontend .gymtrack-card{background:linear-gradient(145deg,#181b1e,#0d0f11);border:1px solid rgba(255,255,255,.09);border-radius:18px;padding:24px;margin-bottom:20px;box-sizing:border-box;box-shadow:0 15px 45px rgba(0,0,0,.2)}.gymtrack-frontend table{width:100%;border-collapse:collapse}.gymtrack-frontend th,.gymtrack-frontend td{padding:10px;border-bottom:1px solid rgba(255,255,255,.08);text-align:left}.gymtrack-frontend th{color:#ff8614;font-size:11px;text-transform:uppercase;letter-spacing:.08em}.gymtrack-frontend input,.gymtrack-frontend select,.gymtrack-frontend textarea{max-width:100%;box-sizing:border-box;padding:10px;border-radius:6px;border:1px solid #444;background:#111;color:#fff}.gymtrack-frontend label{font-weight:600;display:block;margin-bottom:6px}.gymtrack-frontend .field{margin-bottom:16px}.gymtrack-frontend .grid{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:16px}.gymtrack-frontend .button{display:inline-block;padding:11px 17px;border:0;border-radius:7px;background:#ff8614;color:#080a0c;text-decoration:none;cursor:pointer;font-weight:800}.gymtrack-frontend .button.secondary{background:#444;color:#fff}.gymtrack-frontend .message{padding:12px 16px;border-radius:7px;margin-bottom:20px;background:#111c25;border-left:4px solid #ff8614}.gymtrack-frontend .message.success{background:#112017;border-left-color:#46b450}.gymtrack-frontend .message.error{background:#261416;border-left-color:#dc3232}.gymtrack-frontend .actions{display:flex;gap:10px;flex-wrap:wrap;margin-top:18px}.gymtrack-frontend .muted{color:#a7aaad}.gymtrack-frontend .card-grid{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:20px}.gymtrack-frontend .exercise-card{border:1px solid rgba(255,255,255,.09);border-radius:12px;padding:18px;background:#111315}.gymtrack-frontend img{max-width:180px;height:auto;border-radius:6px}.gymtrack-fullscreen-page .gymtrack-visual-header-image{max-width:none !important;max-height:none !important;width:100% !important;height:100% !important;object-fit:cover !important;display:block !important}
        .gymtrack-frontend.gymtrack-fullscreen-page{width:100vw !important;max-width:none !important;margin:0 !important;padding:0 !important;background:#050607;min-height:100vh;overflow:hidden}
        .gymtrack-fullscreen-page .gymtrack-visual-header{position:relative;width:100%;height:100vh;min-height:620px;margin:0 0 45px;padding:0;border:0;border-radius:0;overflow:hidden;background:#050607;box-shadow:none;isolation:isolate}
        body.admin-bar .gymtrack-fullscreen-page .gymtrack-visual-header{height:calc(100vh - 32px);min-height:588px}
        .gymtrack-fullscreen-page .gymtrack-visual-header-image{position:absolute;inset:0;width:100%;height:100%;display:block !important;visibility:visible !important;opacity:1 !important;object-fit:cover;object-position:center center;background:#050607;z-index:0}
        .gymtrack-fullscreen-page .gymtrack-visual-header-overlay{position:absolute;inset:0;background:linear-gradient(180deg,rgba(0,0,0,.10) 0%,rgba(0,0,0,.08) 50%,rgba(0,0,0,.72) 100%),linear-gradient(90deg,rgba(3,5,7,.45) 0%,rgba(3,5,7,.08) 55%,rgba(3,5,7,.42) 100%);z-index:1;pointer-events:none}
        .gymtrack-fullscreen-page .gymtrack-visual-header-content{position:absolute;left:clamp(22px,5vw,90px);right:clamp(22px,5vw,90px);bottom:clamp(34px,7vh,82px);z-index:3;padding:0;max-width:900px;text-shadow:0 3px 20px rgba(0,0,0,.75)}
        .gymtrack-fullscreen-page .gymtrack-visual-header-kicker{margin:0 0 10px;color:#ff8614;font-size:13px;font-weight:900;letter-spacing:.24em;text-transform:uppercase}
        .gymtrack-fullscreen-page .gymtrack-visual-header h1{margin:0;color:#fff;font-family:Impact,Haettenschweiler,"Arial Black",sans-serif;font-size:clamp(54px,8vw,118px);line-height:.86;text-transform:uppercase;letter-spacing:-.035em}
        .gymtrack-fullscreen-page .gymtrack-visual-header-content>p:last-child{max-width:680px;margin:17px 0 0;color:rgba(255,255,255,.90);font-size:clamp(15px,1.4vw,21px);line-height:1.45}
        .gymtrack-fullscreen-page .gymtrack-home-back{position:absolute;top:clamp(18px,3vh,32px);left:clamp(18px,3vw,45px);z-index:5;display:inline-flex;align-items:center;gap:9px;padding:12px 18px;border:1px solid rgba(255,255,255,.28);border-radius:999px;background:rgba(5,7,9,.72);backdrop-filter:blur(10px);color:#fff;text-decoration:none;font-weight:800;font-size:14px;letter-spacing:.04em;text-transform:uppercase;box-shadow:0 8px 25px rgba(0,0,0,.3);transition:.2s ease}
        .gymtrack-fullscreen-page .gymtrack-home-back:hover{background:#ff8614;color:#080a0c;border-color:#ff8614;transform:translateY(-1px)}
        .gymtrack-fullscreen-page .gymtrack-home-back span{font-size:22px;line-height:1}
        .gymtrack-fullscreen-page > h1,.gymtrack-fullscreen-page > p,.gymtrack-fullscreen-page > .message,.gymtrack-fullscreen-page > .gymtrack-card,.gymtrack-fullscreen-page > .actions{max-width:1180px;margin-left:auto;margin-right:auto}
        .gymtrack-fullscreen-page > h1{margin-top:0;padding-left:20px;padding-right:20px}
        .gymtrack-fullscreen-page > p{padding-left:20px;padding-right:20px}
        .gymtrack-fullscreen-page > .actions{padding-bottom:30px}
        @media(max-width:700px){.gymtrack-frontend .grid,.gymtrack-frontend .card-grid{grid-template-columns:1fr}.gymtrack-frontend{padding:0 12px}.gymtrack-frontend table{font-size:14px}.gymtrack-fullscreen-page .gymtrack-visual-header{height:100svh;min-height:520px;margin-left:0;width:100%;margin-bottom:35px} body.admin-bar .gymtrack-fullscreen-page .gymtrack-visual-header{height:calc(100svh - 46px);min-height:480px}.gymtrack-fullscreen-page .gymtrack-visual-header-content{left:20px;right:20px;bottom:30px}.gymtrack-fullscreen-page .gymtrack-home-back{top:15px;left:15px;padding:10px 14px;font-size:12px}.gymtrack-fullscreen-page .gymtrack-visual-header h1{font-size:clamp(46px,15vw,78px)}.gymtrack-fullscreen-page .gymtrack-visual-header-content>p:last-child{font-size:14px}}
    </style>';;
}

function gymtrack_frontend_asset_url( $relative_path ) {
    $file_path = plugin_dir_path( __FILE__ ) . ltrim( $relative_path, '/\\' );
    $url = plugins_url( ltrim( $relative_path, '/\\' ), __FILE__ );
    if ( file_exists( $file_path ) ) {
        $url = add_query_arg( 'v', (string) filemtime( $file_path ), $url );
    }
    return $url;
}

function gymtrack_frontend_visual_header( $title, $subtitle, $image_url, $kicker = 'GYMTRACK' ) {
    $front_page_id = (int) get_option( 'page_on_front' );
    $home_url = $front_page_id > 0 ? get_permalink( $front_page_id ) : home_url( '/' );
    return '<div class="gymtrack-visual-header"><img class="gymtrack-visual-header-image" src="' . esc_url( $image_url ) . '" alt="' . esc_attr( $title ) . '"><div class="gymtrack-visual-header-overlay"></div><a class="gymtrack-home-back" href="' . esc_url( $home_url ) . '" aria-label="Volver a la pantalla de inicio"><span aria-hidden="true">‹</span> Inicio</a><div class="gymtrack-visual-header-content"><p class="gymtrack-visual-header-kicker">' . esc_html( $kicker ) . '</p><h1>' . esc_html( $title ) . '</h1><p>' . esc_html( $subtitle ) . '</p></div></div>';
}

function gymtrack_frontend_register_shortcode() {
    $access = gymtrack_frontend_access_check();
    if ( '' !== $access ) { return $access; }
    global $wpdb;
    $table_exercises = $wpdb->prefix . 'gymtrack_exercises';
    $table_workouts = $wpdb->prefix . 'gymtrack_workouts';
    $table_sets = $wpdb->prefix . 'gymtrack_sets';
    $table_routines = $wpdb->prefix . 'gymtrack_routines';
    $table_routine_exercises = $wpdb->prefix . 'gymtrack_routine_exercises';
    $message = '';
    $message_class = 'message';

    if ( isset($_POST['gymtrack_frontend_register']) && isset($_POST['gymtrack_frontend_nonce']) && wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['gymtrack_frontend_nonce'])), 'gymtrack_frontend_register') ) {
        $date = sanitize_text_field(isset($_POST['workout_date']) ? wp_unslash($_POST['workout_date']) : '');
        $notes = sanitize_textarea_field(isset($_POST['notes']) ? wp_unslash($_POST['notes']) : '');
        $routine_id = absint(isset($_POST['routine_id']) ? $_POST['routine_id'] : 0);
        $rows = array();

        $date_object = DateTime::createFromFormat( 'Y-m-d', $date );
        $date_valid = $date_object && $date_object->format( 'Y-m-d' ) === $date;

        if ( '' === $date ) {
            $message = 'La fecha es obligatoria.';
            $message_class = 'message error';
        } elseif ( ! $date_valid ) {
            $message = 'La fecha no tiene un formato válido. Usa AAAA-MM-DD.';
            $message_class = 'message error';
        } elseif ( $routine_id > 0 && isset($_POST['routine_exercises']) && is_array($_POST['routine_exercises']) ) {
            $routine_exists = $wpdb->get_var($wpdb->prepare("SELECT id FROM $table_routines WHERE id=%d LIMIT 1", $routine_id));
            if ( ! $routine_exists ) {
                $message = 'La rutina seleccionada no existe.'; $message_class = 'message error';
            } else {
                foreach ( $_POST['routine_exercises'] as $row ) {
                    if ( ! is_array($row) ) { continue; }
                    $exercise_id = absint(isset($row['exercise_id']) ? $row['exercise_id'] : 0);
                    $weight = floatval(isset($row['weight']) ? $row['weight'] : 0);
                    $sets = absint(isset($row['sets']) ? $row['sets'] : 0);
                    $reps = absint(isset($row['repetitions']) ? $row['repetitions'] : 0);
                    $row_notes = sanitize_textarea_field(isset($row['notes']) ? wp_unslash($row['notes']) : '');
                    $belongs_to_routine = $wpdb->get_var(
                        $wpdb->prepare(
                            "SELECT id FROM $table_routine_exercises WHERE routine_id=%d AND exercise_id=%d LIMIT 1",
                            $routine_id,
                            $exercise_id
                        )
                    );
                    $exists = $wpdb->get_var($wpdb->prepare("SELECT id FROM $table_exercises WHERE id=%d LIMIT 1", $exercise_id));
                    if ( $exists && $belongs_to_routine && $weight > 0 && $sets > 0 && $reps > 0 ) {
                        $rows[] = array('exercise_id'=>$exercise_id,'weight'=>$weight,'sets'=>$sets,'repetitions'=>$reps,'notes'=>$row_notes);
                    }
                }
            }
        } else {
            $exercise_id = absint(isset($_POST['exercise_id']) ? $_POST['exercise_id'] : 0);
            $weight = floatval(isset($_POST['weight']) ? $_POST['weight'] : 0);
            $sets = absint(isset($_POST['sets']) ? $_POST['sets'] : 0);
            $reps = absint(isset($_POST['repetitions']) ? $_POST['repetitions'] : 0);
            $exists = $wpdb->get_var($wpdb->prepare("SELECT id FROM $table_exercises WHERE id=%d LIMIT 1", $exercise_id));
            if ( $exists && $weight > 0 && $sets > 0 && $reps > 0 ) {
                $rows[] = array('exercise_id'=>$exercise_id,'weight'=>$weight,'sets'=>$sets,'repetitions'=>$reps,'notes'=>$notes);
            } else {
                $message = 'Fecha, ejercicio, peso, series y repeticiones son obligatorios. El peso debe ser mayor que 0.';
                $message_class = 'message error';
            }
        }

        if ( empty($message) && !empty($rows) ) {
            $wpdb->query('START TRANSACTION');
            $created = $wpdb->insert($table_workouts,array('user_id'=>get_current_user_id(),'workout_date'=>$date,'notes'=>$notes),array('%d','%s','%s'));
            $all = (bool) $created;
            $workout_id = $wpdb->insert_id;
            if ( $all ) {
                foreach ( $rows as $row ) {
                    if ( ! $wpdb->insert($table_sets,array('workout_id'=>$workout_id,'exercise_id'=>$row['exercise_id'],'weight'=>$row['weight'],'sets'=>$row['sets'],'repetitions'=>$row['repetitions'],'notes'=>$row['notes']),array('%d','%d','%f','%d','%d','%s')) ) { $all=false; break; }
                }
            }
            if ( $all ) { $wpdb->query('COMMIT'); $message='Entrenamiento registrado correctamente.'; $message_class='message success'; }
            else { $wpdb->query('ROLLBACK'); $message='No se ha podido guardar el entrenamiento completo.'; $message_class='message error'; }
        } elseif ( empty($message) ) {
            $message = 'Debes indicar al menos un ejercicio válido, con peso mayor que 0, series y repeticiones.';
            $message_class = 'message error';
        }
    }

    $exercises = $wpdb->get_results("SELECT id,name,muscle_group FROM $table_exercises ORDER BY name ASC");
    $routines = $wpdb->get_results("SELECT id,name,description FROM $table_routines ORDER BY name ASC");
    $routine_data = array();
    foreach ( $routines as $routine ) {
        $routine_data[$routine->id] = array('description'=>$routine->description,'exercises'=>$wpdb->get_results($wpdb->prepare("SELECT re.exercise_order,re.target_sets,re.target_repetitions,re.notes,e.id AS exercise_id,e.name AS exercise_name,e.muscle_group FROM $table_routine_exercises re INNER JOIN $table_exercises e ON re.exercise_id=e.id WHERE re.routine_id=%d ORDER BY re.exercise_order ASC,re.id ASC",$routine->id),ARRAY_A));
    }
    ob_start(); echo gymtrack_frontend_styles();
    ?>
    <div class="gymtrack-frontend gymtrack-fullscreen-page">
        <?php echo gymtrack_frontend_visual_header( 'Registra', 'Guarda tus entrenamientos y mantén el control de cada sesión.', gymtrack_frontend_asset_url( 'assets/gymtrack-registra.jpg' ) ); ?>
        <h1>Registrar entrenamiento</h1>
        <p class="muted">Registra tu entrenamiento manualmente o utiliza una rutina guardada.</p>
        <?php if ( $message ) : ?><div class="<?php echo esc_attr($message_class); ?>"><p><?php echo esc_html($message); ?></p></div><?php endif; ?>
        <div class="gymtrack-card">
            <form method="post">
                <?php wp_nonce_field('gymtrack_frontend_register','gymtrack_frontend_nonce'); ?>
                <div class="grid">
                    <div class="field"><label for="gt-date">Fecha</label><input id="gt-date" type="date" name="workout_date" value="<?php echo esc_attr(isset($_POST['workout_date']) ? wp_unslash($_POST['workout_date']) : current_time('Y-m-d')); ?>" required></div>
                    <div class="field"><label for="gt-routine">Rutina</label><select id="gt-routine" name="routine_id"><option value="0">Registro manual</option><?php foreach($routines as $routine): ?><option value="<?php echo esc_attr($routine->id); ?>"><?php echo esc_html($routine->name); ?></option><?php endforeach; ?></select></div>
                </div>
                <div id="gt-manual">
                    <div class="grid">
                        <div class="field"><label for="gt-exercise">Ejercicio</label><select id="gt-exercise" name="exercise_id"><option value="0">Selecciona un ejercicio</option><?php foreach($exercises as $exercise): ?><option value="<?php echo esc_attr($exercise->id); ?>"><?php echo esc_html($exercise->name); ?> — <?php echo esc_html($exercise->muscle_group); ?></option><?php endforeach; ?></select></div>
                        <div class="field"><label for="gt-weight">Peso (kg)</label><input id="gt-weight" type="number" name="weight" min="0.5" step="0.5" value="" /></div>
                        <div class="field"><label for="gt-sets">Series</label><input id="gt-sets" type="number" name="sets" min="1" value="" /></div>
                        <div class="field"><label for="gt-reps">Repeticiones</label><input id="gt-reps" type="number" name="repetitions" min="1" value="" /></div>
                    </div>
                    <div class="field"><label for="gt-notes">Notas</label><textarea id="gt-notes" name="notes" rows="3"></textarea></div>
                </div>
                <div id="gt-routine" style="display:none"><div id="gt-routine-description" class="message" style="display:none"></div><div style="overflow-x:auto"><table><thead><tr><th>Orden</th><th>Ejercicio</th><th>Series</th><th>Repeticiones</th><th>Peso kg</th><th>Notas</th></tr></thead><tbody id="gt-routine-body"></tbody></table></div></div>
                <div class="actions"><button class="button" type="submit" name="gymtrack_frontend_register" value="1">Guardar entrenamiento</button><a class="button secondary" href="<?php echo esc_url(home_url('/gymtrack/')); ?>">Volver a GymTrack</a></div>
            </form>
        </div>
    </div>
    <script>
    document.addEventListener('DOMContentLoaded',function(){
      const select=document.getElementById('gt-routine'),manual=document.getElementById('gt-manual'),routine=document.getElementById('gt-routine'),body=document.getElementById('gt-routine-body'),desc=document.getElementById('gt-routine-description');
      const data=<?php echo wp_json_encode($routine_data); ?>;
      function load(){ body.innerHTML='';desc.innerHTML='';desc.style.display='none'; if(!select.value||select.value==='0'||!data[select.value]){manual.style.display='';routine.style.display='none';return;} manual.style.display='none';routine.style.display=''; const r=data[select.value]; if(r.description){desc.textContent=r.description;desc.style.display='block';} (r.exercises||[]).forEach(function(e,i){const tr=document.createElement('tr');tr.innerHTML='<td>'+e.exercise_order+'</td><td><strong>'+e.exercise_name+'</strong><br><span class="muted">'+e.muscle_group+'</span><input type="hidden" name="routine_exercises['+i+'][exercise_id]" value="'+e.exercise_id+'"></td><td><input required min="1" type="number" name="routine_exercises['+i+'][sets]" value="'+e.target_sets+'"></td><td><input required min="1" type="number" name="routine_exercises['+i+'][repetitions]" value="'+e.target_repetitions+'"></td><td><input required min="0.5" step="0.5" type="number" name="routine_exercises['+i+'][weight]" value=""></td><td><textarea rows="2" name="routine_exercises['+i+'][notes]"></textarea></td>';body.appendChild(tr);});}
      select.addEventListener('change',load);load();
    });
    </script>
    <?php return ob_get_clean();
}
add_shortcode('gymtrack_register','gymtrack_frontend_register_shortcode');

function gymtrack_frontend_history_shortcode() {
    $access=gymtrack_frontend_access_check(); if(''!==$access){return $access;} global $wpdb;
    $tw=$wpdb->prefix.'gymtrack_workouts';$ts=$wpdb->prefix.'gymtrack_sets';$te=$wpdb->prefix.'gymtrack_exercises';
    $rows=$wpdb->get_results($wpdb->prepare("SELECT w.workout_date,e.name exercise_name,e.muscle_group,s.weight,s.sets,s.repetitions,s.notes FROM $tw w INNER JOIN $ts s ON w.id=s.workout_id INNER JOIN $te e ON s.exercise_id=e.id WHERE w.user_id=%d AND s.weight>0 ORDER BY w.workout_date DESC,w.id DESC,s.id ASC",get_current_user_id()));
    ob_start();echo gymtrack_frontend_styles();?><div class="gymtrack-frontend gymtrack-fullscreen-page"><?php echo gymtrack_frontend_visual_header( 'Analiza', 'Revisa tus entrenamientos y sigue tu evolución sesión a sesión.', gymtrack_frontend_asset_url( 'assets/gymtrack-analiza.jpg' ) ); ?><h1>Mi historial</h1><p class="muted">Tus entrenamientos registrados en GymTrack.</p><div class="gymtrack-card"><?php if(empty($rows)):?><p>No hay entrenamientos válidos registrados todavía.</p><?php else:?><div style="overflow-x:auto"><table><thead><tr><th>Fecha</th><th>Ejercicio</th><th>Grupo muscular</th><th>Peso</th><th>Series</th><th>Repeticiones</th><th>Notas</th></tr></thead><tbody><?php foreach($rows as $r):?><tr><td><?php echo esc_html($r->workout_date);?></td><td><strong><?php echo esc_html($r->exercise_name);?></strong></td><td><?php echo esc_html($r->muscle_group);?></td><td><?php echo esc_html(number_format_i18n((float)$r->weight,2));?> kg</td><td><?php echo esc_html($r->sets);?></td><td><?php echo esc_html($r->repetitions);?></td><td><?php echo esc_html($r->notes);?></td></tr><?php endforeach;?></tbody></table></div><?php endif;?></div><div class="actions"><a class="button" href="<?php echo esc_url(gymtrack_frontend_page_url('gymtrack-registrar'));?>">Registrar entrenamiento</a><a class="button secondary" href="<?php echo esc_url(home_url('/gymtrack/'));?>">Volver a GymTrack</a></div></div><?php return ob_get_clean();
}
add_shortcode('gymtrack_history','gymtrack_frontend_history_shortcode');

function gymtrack_frontend_progress_shortcode() {
    $access=gymtrack_frontend_access_check(); if(''!==$access){return $access;} global $wpdb;
    $tw=$wpdb->prefix.'gymtrack_workouts';$ts=$wpdb->prefix.'gymtrack_sets';$te=$wpdb->prefix.'gymtrack_exercises';
    $rows=$wpdb->get_results($wpdb->prepare("SELECT e.id exercise_id,e.name exercise_name,w.workout_date,s.weight,s.sets,s.repetitions FROM $tw w INNER JOIN $ts s ON w.id=s.workout_id INNER JOIN $te e ON s.exercise_id=e.id WHERE w.user_id=%d AND s.weight>0 ORDER BY e.name ASC,w.workout_date ASC,w.id ASC",get_current_user_id()));
    $best=array();$chart=array();foreach($rows as $r){$chart[$r->exercise_id]['name']=$r->exercise_name;$chart[$r->exercise_id]['labels'][]=$r->workout_date;$chart[$r->exercise_id]['data'][]=(float)$r->weight;if(!isset($best[$r->exercise_id])||(float)$r->weight>(float)$best[$r->exercise_id]->weight){$best[$r->exercise_id]=$r;}}
    ob_start();echo gymtrack_frontend_styles();?><div class="gymtrack-frontend gymtrack-fullscreen-page"><?php echo gymtrack_frontend_visual_header( 'Supérate', 'Alcanza tus objetivos y convierte tus mejores marcas en nuevos retos.', gymtrack_frontend_asset_url( 'assets/gymtrack-superate.jpg' ) ); ?><h1>Mi progreso</h1><p class="muted">Evolución de tus pesos y mejores marcas.</p><div class="gymtrack-card"><h2>Mejores marcas</h2><?php if(empty($best)):?><p>No hay datos de progreso todavía.</p><?php else:?><table><thead><tr><th>Ejercicio</th><th>Mejor peso</th><th>Fecha</th></tr></thead><tbody><?php foreach($best as $r):?><tr><td><?php echo esc_html($r->exercise_name);?></td><td><?php echo esc_html(number_format_i18n((float)$r->weight,2));?> kg</td><td><?php echo esc_html($r->workout_date);?></td></tr><?php endforeach;?></tbody></table><?php endif;?></div><div class="gymtrack-card"><h2>Evolución</h2><?php if(empty($chart)):?><p>No hay datos para mostrar.</p><?php else:?><div class="field"><label for="gt-progress-exercise">Ejercicio</label><select id="gt-progress-exercise"><?php foreach($chart as $id=>$data):?><option value="<?php echo esc_attr($id);?>"><?php echo esc_html($data['name']);?></option><?php endforeach;?></select></div><canvas id="gt-progress-chart" height="120"></canvas><?php endif;?></div><div class="actions"><a class="button" href="<?php echo esc_url(gymtrack_frontend_page_url('gymtrack-registrar'));?>">Registrar entrenamiento</a><a class="button secondary" href="<?php echo esc_url(home_url('/gymtrack/'));?>">Volver a GymTrack</a></div></div>
    <?php if(!empty($chart)):?><script src="https://cdn.jsdelivr.net/npm/chart.js"></script><script>document.addEventListener('DOMContentLoaded',function(){const data=<?php echo wp_json_encode($chart);?>;const sel=document.getElementById('gt-progress-exercise'),ctx=document.getElementById('gt-progress-chart').getContext('2d');let chart;function draw(){const d=data[sel.value];if(chart){chart.destroy();}chart=new Chart(ctx,{type:'line',data:{labels:d.labels,datasets:[{label:d.name+' (kg)',data:d.data,tension:.2}]},options:{responsive:true,scales:{y:{beginAtZero:false}}}});}sel.addEventListener('change',draw);draw();});</script><?php endif;?><?php return ob_get_clean();
}
add_shortcode('gymtrack_progress','gymtrack_frontend_progress_shortcode');

function gymtrack_frontend_library_shortcode() {
    $access=gymtrack_frontend_access_check(); if(''!==$access){return $access;}
    $search=sanitize_text_field(isset($_GET['gymtrack_search'])?wp_unslash($_GET['gymtrack_search']):'');$results=array();$error='';
    if(''!==$search){$cache_key='gymtrack_wger_'.md5(strtolower($search));$cached=get_transient($cache_key);if(false!==$cached){$results=$cached;}else{$response=wp_remote_get(add_query_arg(array('name__search'=>$search,'limit'=>12),'https://wger.de/api/v2/exerciseinfo/'),array('timeout'=>10,'headers'=>array('Accept'=>'application/json')));if(is_wp_error($response)){$error=$response->get_error_message();}else{$code=wp_remote_retrieve_response_code($response);$body=json_decode(wp_remote_retrieve_body($response),true);if(200!==$code||!is_array($body)){$error='No se ha podido consultar la biblioteca externa.';}else{$results=isset($body['results'])&&is_array($body['results'])?$body['results']:array();set_transient($cache_key,$results,10*MINUTE_IN_SECONDS);}}}}
    ob_start();echo gymtrack_frontend_styles();?><div class="gymtrack-frontend gymtrack-fullscreen-page"><?php echo gymtrack_frontend_visual_header( 'Buscar ejercicios', 'Explora la biblioteca y encuentra el ejercicio que necesitas.', gymtrack_frontend_asset_url( 'assets/gymtrack-buscar.jpg' ) ); ?><h1>Biblioteca de ejercicios</h1><p class="muted">Consulta ejercicios de la biblioteca pública de wger.</p><div class="gymtrack-card"><form method="get" action="<?php echo esc_url( gymtrack_frontend_page_url( 'gymtrack-biblioteca' ) ); ?>"><div class="field"><label for="gt-search">Buscar ejercicio</label><input id="gt-search" type="search" name="gymtrack_search" value="<?php echo esc_attr($search);?>" placeholder="Ej.: press banca"><button class="button" type="submit">Buscar</button></div></form></div><?php if($error):?><div class="message error"><p><?php echo esc_html($error);?></p></div><?php endif;?><?php if(''!==$search&&!$error):?><div class="card-grid"><?php foreach($results as $item){$name='';$description='';$category='';$translations=isset($item['translations'])&&is_array($item['translations'])?$item['translations']:array();foreach($translations as $tr){if(isset($tr['language'])&&is_array($tr['language'])&&isset($tr['language']['short_name'])&&'es'===$tr['language']['short_name']){$name=$tr['name']??'';$description=wp_strip_all_tags($tr['description']??'');break;}}if(''===$name&&isset($item['name']))$name=$item['name'];if(''===$description&&isset($item['description']))$description=wp_strip_all_tags($item['description']);if(isset($item['category'])&&is_array($item['category']))$category=$item['category']['name']??'';?><div class="exercise-card"><h2><?php echo esc_html($name?:'Ejercicio');?></h2><?php if($category):?><p><strong>Categoría:</strong> <?php echo esc_html($category);?></p><?php endif;?><?php if($description):?><p><?php echo esc_html(wp_trim_words($description,45));?></p><?php endif;?><?php if(isset($item['id'])):?><p class="muted">ID externo: <?php echo esc_html($item['id']);?></p><?php endif;?></div><?php }?></div><?php elseif(''===$search):?><div class="gymtrack-card"><p>Introduce un término para buscar ejercicios.</p></div><?php endif;?><div class="actions"><a class="button secondary" href="<?php echo esc_url(home_url('/gymtrack/'));?>">Volver a GymTrack</a></div></div><?php return ob_get_clean();
}
add_shortcode('gymtrack_library','gymtrack_frontend_library_shortcode');

function gymtrack_ensure_frontend_pages() {
    $pages=array(
        'gymtrack-registrar'=>array('Registrar entrenamiento','[gymtrack_register]'),
        'gymtrack-historial'=>array('Mi historial','[gymtrack_history]'),
        'gymtrack-progreso'=>array('Mi progreso','[gymtrack_progress]'),
        'gymtrack-biblioteca'=>array('Biblioteca de ejercicios','[gymtrack_library]'),
    );
    foreach($pages as $slug=>$data){$page=get_page_by_path($slug);if($page instanceof WP_Post){if(!has_shortcode($page->post_content,trim($data[1],'[]'))){wp_update_post(array('ID'=>$page->ID,'post_content'=>$data[1]));}}else{$id=wp_insert_post(array('post_title'=>$data[0],'post_name'=>$slug,'post_content'=>$data[1],'post_status'=>'publish','post_type'=>'page'),true);}}
}
add_action( 'admin_init', 'gymtrack_ensure_frontend_pages', 20 );


function gymtrack_ensure_frontend_dashboard_page() {

    $saved_page_id = (int) get_option( 'gymtrack_dashboard_page_id' );

    if ( $saved_page_id > 0 ) {
        $saved_page = get_post( $saved_page_id );

        if ( $saved_page instanceof WP_Post && has_shortcode( $saved_page->post_content, 'gymtrack_dashboard' ) ) {
            return;
        }
    }

    $existing_page = get_page_by_path( 'gymtrack' );

    if ( $existing_page instanceof WP_Post ) {
        if ( ! has_shortcode( $existing_page->post_content, 'gymtrack_dashboard' ) ) {
            $new_content = trim( $existing_page->post_content );

            if ( '' !== $new_content ) {
                $new_content .= "\n\n";
            }

            $new_content .= '[gymtrack_dashboard]';

            wp_update_post(
                array(
                    'ID'           => $existing_page->ID,
                    'post_content' => $new_content,
                )
            );
        }

        update_option( 'gymtrack_dashboard_page_id', $existing_page->ID );
        return;
    }

    $page_id = wp_insert_post(
        array(
            'post_title'   => 'GymTrack',
            'post_name'    => 'gymtrack',
            'post_content' => '[gymtrack_dashboard]',
            'post_status'  => 'publish',
            'post_type'    => 'page',
        ),
        true
    );

    if ( ! is_wp_error( $page_id ) ) {
        update_option( 'gymtrack_dashboard_page_id', $page_id );
    }
}

add_action( 'admin_init', 'gymtrack_ensure_frontend_dashboard_page', 20 );


/**
 * =========================================================
 * CONFIGURACION DEL SITIO: GYMTRACK COMO PAGINA PRINCIPAL
 * =========================================================
 *
 * GymTrack es la aplicacion principal del sitio. WordPress se
 * configura para mostrar el dashboard de GymTrack como portada
 * y se eliminan de la papelera los contenidos de demostracion
 * que crea una instalacion nueva de WordPress.
 */
function gymtrack_configure_site_as_app() {

    $dashboard_id = (int) get_option( 'gymtrack_dashboard_page_id' );

    if ( $dashboard_id <= 0 ) {
        $dashboard = get_page_by_path( 'gymtrack' );
        if ( $dashboard instanceof WP_Post ) {
            $dashboard_id = (int) $dashboard->ID;
            update_option( 'gymtrack_dashboard_page_id', $dashboard_id );
        }
    }

    if ( $dashboard_id > 0 ) {
        update_option( 'show_on_front', 'page' );
        update_option( 'page_on_front', $dashboard_id );
        update_option( 'page_for_posts', 0 );
    }

    // Eliminar de la vista publica el contenido de demostracion de WordPress.
    // Se evita get_page_by_title(), que esta obsoleto en versiones modernas
    // de WordPress, y se consulta directamente por titulo y tipo de contenido.
    $demo_titles = array( '¡Hola, mundo!', 'Hello world!' );

    foreach ( $demo_titles as $demo_title ) {
        $demo_posts = get_posts(
            array(
                'post_type'              => 'post',
                'post_status'            => array( 'publish', 'draft', 'pending', 'private', 'future' ),
                'title'                  => $demo_title,
                'posts_per_page'         => 1,
                'suppress_filters'       => true,
                'no_found_rows'          => true,
                'update_post_meta_cache' => false,
                'update_post_term_cache' => false,
            )
        );

        if ( ! empty( $demo_posts ) && $demo_posts[0] instanceof WP_Post ) {
            wp_trash_post( $demo_posts[0]->ID );
        }
    }

    $sample_page = get_page_by_path( 'pagina-de-ejemplo' );
    if ( ! $sample_page instanceof WP_Post ) {
        $sample_page = get_page_by_path( 'sample-page' );
    }

    if ( $sample_page instanceof WP_Post ) {
        wp_trash_post( $sample_page->ID );
    }
}
add_action( 'admin_init', 'gymtrack_configure_site_as_app', 30 );

/**
 * =========================================================
 * LIMPIEZA VISUAL DEL TEMA: GYMTRACK COMO APLICACION
 * =========================================================
 *
 * Twenty Twenty-Five incluye un pie de pagina con enlaces de
 * demostracion (Blog, Acerca de, FAQs, Autores, Tienda, etc.).
 * GymTrack no necesita ese contenido, por lo que se oculta el
 * footer publico del tema y la aplicacion queda como interfaz
 * principal.
 */
function gymtrack_app_public_styles() {
    if ( is_admin() ) {
        return;
    }

    echo '<style id="gymtrack-app-public-styles">
        /* Ocultar el footer de demostracion del tema Twenty Twenty-Five. */
        body.gymtrack-app-page footer.wp-block-template-part,
        body.gymtrack-app-page > footer,
        body.gymtrack-app-page .wp-site-blocks > footer {
            display: none !important;
        }
    </style>';
}
add_action( 'wp_head', 'gymtrack_app_public_styles', 100 );

function gymtrack_mark_public_app_body( $classes ) {
    $classes[] = 'gymtrack-app-page';
    return $classes;
}
add_filter( 'body_class', 'gymtrack_mark_public_app_body' );

function gymtrack_mark_home_body_class( $classes ) {
    if ( is_page( 'gymtrack' ) ) {
        $classes[] = 'gymtrack-home-page';
    }

    return $classes;
}
add_filter( 'body_class', 'gymtrack_mark_home_body_class' );



/**
 * =========================================================
 * REST API DE GYMTRACK
 * =========================================================
 *
 * Endpoints de lectura para integrar GymTrack con aplicaciones
 * externas. Los endpoints requieren un usuario de WordPress
 * con la capacidad gymtrack_use.
 */

function gymtrack_rest_permission() {

    return gymtrack_can_use();
}


function gymtrack_rest_exercises( $request ) {

    global $wpdb;

    $table_exercises = $wpdb->prefix . 'gymtrack_exercises';

    $exercises = $wpdb->get_results(
        "SELECT id, name, muscle_group, description
        FROM $table_exercises
        ORDER BY name ASC",
        ARRAY_A
    );

    return rest_ensure_response( $exercises );
}


function gymtrack_rest_routines( $request ) {

    global $wpdb;

    $table_routines = $wpdb->prefix . 'gymtrack_routines';
    $table_routine_exercises = $wpdb->prefix . 'gymtrack_routine_exercises';
    $table_exercises = $wpdb->prefix . 'gymtrack_exercises';

    $routines = $wpdb->get_results(
        "SELECT id, name, description, created_at
        FROM $table_routines
        ORDER BY id DESC",
        ARRAY_A
    );

    foreach ( $routines as &$routine ) {

        $routine['exercises'] = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT
                    re.id,
                    re.exercise_order,
                    re.target_sets,
                    re.target_repetitions,
                    re.rest_seconds,
                    re.notes,
                    e.id AS exercise_id,
                    e.name AS exercise_name,
                    e.muscle_group
                FROM $table_routine_exercises re
                INNER JOIN $table_exercises e
                    ON re.exercise_id = e.id
                WHERE re.routine_id = %d
                ORDER BY re.exercise_order ASC, re.id ASC",
                $routine['id']
            ),
            ARRAY_A
        );
    }

    unset( $routine );

    return rest_ensure_response( $routines );
}


function gymtrack_rest_workouts( $request ) {

    global $wpdb;

    $table_workouts = $wpdb->prefix . 'gymtrack_workouts';
    $table_sets = $wpdb->prefix . 'gymtrack_sets';
    $table_exercises = $wpdb->prefix . 'gymtrack_exercises';

    if ( gymtrack_is_admin() ) {

        $query = "SELECT
            w.id AS workout_id,
            w.user_id,
            u.user_login,
            w.workout_date,
            w.notes AS workout_notes,
            s.id AS set_id,
            s.exercise_id,
            e.name AS exercise_name,
            e.muscle_group,
            s.weight,
            s.sets,
            s.repetitions,
            s.notes AS set_notes
        FROM $table_workouts w
        INNER JOIN {$wpdb->users} u
            ON w.user_id = u.ID
        INNER JOIN $table_sets s
            ON w.id = s.workout_id
        INNER JOIN $table_exercises e
            ON s.exercise_id = e.id
        WHERE s.weight > 0
        ORDER BY w.workout_date DESC, w.id DESC, s.id ASC";

        $workouts = $wpdb->get_results( $query, ARRAY_A );

    } else {

        $current_user_id = get_current_user_id();

        $workouts = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT
                    w.id AS workout_id,
                    w.user_id,
                    u.user_login,
                    w.workout_date,
                    w.notes AS workout_notes,
                    s.id AS set_id,
                    s.exercise_id,
                    e.name AS exercise_name,
                    e.muscle_group,
                    s.weight,
                    s.sets,
                    s.repetitions,
                    s.notes AS set_notes
                FROM $table_workouts w
                INNER JOIN {$wpdb->users} u
                    ON w.user_id = u.ID
                INNER JOIN $table_sets s
                    ON w.id = s.workout_id
                INNER JOIN $table_exercises e
                    ON s.exercise_id = e.id
                WHERE w.user_id = %d
                AND s.weight > 0
                ORDER BY w.workout_date DESC, w.id DESC, s.id ASC",
                $current_user_id
            ),
            ARRAY_A
        );
    }

    return rest_ensure_response( $workouts );
}


function gymtrack_rest_history( $request ) {

    return gymtrack_rest_workouts( $request );
}


function gymtrack_rest_progress( $request ) {

    global $wpdb;

    $table_workouts = $wpdb->prefix . 'gymtrack_workouts';
    $table_sets = $wpdb->prefix . 'gymtrack_sets';
    $table_exercises = $wpdb->prefix . 'gymtrack_exercises';

    if ( gymtrack_is_admin() ) {

        $progress = $wpdb->get_results(
            "SELECT
                w.user_id,
                u.user_login,
                e.id AS exercise_id,
                e.name AS exercise_name,
                w.workout_date,
                s.weight,
                s.sets,
                s.repetitions
            FROM $table_workouts w
            INNER JOIN {$wpdb->users} u
                ON w.user_id = u.ID
            INNER JOIN $table_sets s
                ON w.id = s.workout_id
            INNER JOIN $table_exercises e
                ON s.exercise_id = e.id
            WHERE s.weight > 0
            ORDER BY e.name ASC, w.workout_date ASC, w.id ASC",
            ARRAY_A
        );

    } else {

        $current_user_id = get_current_user_id();

        $progress = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT
                    w.user_id,
                    u.user_login,
                    e.id AS exercise_id,
                    e.name AS exercise_name,
                    w.workout_date,
                    s.weight,
                    s.sets,
                    s.repetitions
                FROM $table_workouts w
                INNER JOIN {$wpdb->users} u
                    ON w.user_id = u.ID
                INNER JOIN $table_sets s
                    ON w.id = s.workout_id
                INNER JOIN $table_exercises e
                    ON s.exercise_id = e.id
                WHERE s.weight > 0
                AND w.user_id = %d
                ORDER BY e.name ASC, w.workout_date ASC, w.id ASC",
                $current_user_id
            ),
            ARRAY_A
        );
    }

    return rest_ensure_response( $progress );
}


function gymtrack_register_rest_routes() {

    register_rest_route(
        'gymtrack/v1',
        '/exercises',
        array(
            'methods'             => WP_REST_Server::READABLE,
            'callback'            => 'gymtrack_rest_exercises',
            'permission_callback' => 'gymtrack_rest_permission',
        )
    );

    register_rest_route(
        'gymtrack/v1',
        '/routines',
        array(
            'methods'             => WP_REST_Server::READABLE,
            'callback'            => 'gymtrack_rest_routines',
            'permission_callback' => 'gymtrack_rest_permission',
        )
    );

    register_rest_route(
        'gymtrack/v1',
        '/workouts',
        array(
            'methods'             => WP_REST_Server::READABLE,
            'callback'            => 'gymtrack_rest_workouts',
            'permission_callback' => 'gymtrack_rest_permission',
        )
    );

    register_rest_route(
        'gymtrack/v1',
        '/history',
        array(
            'methods'             => WP_REST_Server::READABLE,
            'callback'            => 'gymtrack_rest_history',
            'permission_callback' => 'gymtrack_rest_permission',
        )
    );

    register_rest_route(
        'gymtrack/v1',
        '/progress',
        array(
            'methods'             => WP_REST_Server::READABLE,
            'callback'            => 'gymtrack_rest_progress',
            'permission_callback' => 'gymtrack_rest_permission',
        )
    );
}

add_action( 'rest_api_init', 'gymtrack_register_rest_routes' );


/**
 * =========================================================
 * INTEGRACIÓN CON API EXTERNA - WGER
 * =========================================================
 *
 * GymTrack consulta la API pública de wger para buscar ejercicios
 * y mostrar información adicional que no está almacenada localmente.
 *
 * No se modifica la base de datos local: la integración es de lectura
 * y sirve como fuente externa de información para el usuario.
 */

function gymtrack_external_api_page() {

    if ( ! gymtrack_can_use() ) {
        return;
    }

    $search = '';
    $results = array();
    $error   = '';
    $searched = false;

    if ( isset( $_GET['gymtrack_external_search'] ) ) {

        $searched = true;

        $search = isset( $_GET['gymtrack_external_term'] )
            ? sanitize_text_field( wp_unslash( $_GET['gymtrack_external_term'] ) )
            : '';

        if ( '' === $search ) {

            $error = 'Introduce el nombre de un ejercicio para buscar.';

        } elseif ( strlen( $search ) < 2 ) {

            $error = 'Introduce al menos 2 caracteres.';

        } else {

            $cache_key = 'gymtrack_wger_' . md5( strtolower( $search ) );
            $cached    = get_transient( $cache_key );

            if ( false !== $cached && is_array( $cached ) ) {

                $results = $cached;

            } else {

                $url = add_query_arg(
                    array(
                        'name__search'    => $search,
                        
                        'limit'           => 10,
                    ),
                    'https://wger.de/api/v2/exerciseinfo/'
                );

                $response = wp_remote_get(
                    $url,
                    array(
                        'timeout' => 10,
                        'headers' => array(
                            'Accept' => 'application/json',
                        ),
                    )
                );

                if ( is_wp_error( $response ) ) {

                    $error = 'No se pudo conectar con la API externa: ' . $response->get_error_message();

                } else {

                    $status_code = wp_remote_retrieve_response_code( $response );
                    $body        = wp_remote_retrieve_body( $response );
                    $data        = json_decode( $body, true );

                    if ( 200 !== $status_code ) {

                        $error = 'La API externa respondió con el código HTTP ' . absint( $status_code ) . '.';

                    } elseif ( ! is_array( $data ) || ! isset( $data['results'] ) || ! is_array( $data['results'] ) ) {

                        $error = 'La respuesta de la API externa no tiene el formato esperado.';

                    } else {

                        foreach ( $data['results'] as $item ) {

                            $translations = isset( $item['translations'] ) && is_array( $item['translations'] )
                                ? $item['translations']
                                : array();

                            // Preferimos siempre la traducción española.
                            // wger devuelve las traducciones dentro de exerciseinfo.
                            // En algunas versiones el campo language puede llegar como
                            // código ('es') y en otras como identificador numérico.
                            $translation = array();

                            foreach ( $translations as $candidate ) {
                                $language = isset( $candidate['language'] ) ? $candidate['language'] : '';

                                if ( 'es' === strtolower( (string) $language ) || 4 === absint( $language ) ) {
                                    $translation = $candidate;
                                    break;
                                }

                                if ( isset( $candidate['language']['short_name'] )
                                    && 'es' === strtolower( (string) $candidate['language']['short_name'] ) ) {
                                    $translation = $candidate;
                                    break;
                                }
                            }

                            // Si no existe español, usamos inglés como segundo recurso.
                            if ( empty( $translation ) ) {
                                foreach ( $translations as $candidate ) {
                                    $language = isset( $candidate['language'] ) ? $candidate['language'] : '';

                                    if ( 'en' === strtolower( (string) $language ) || 2 === absint( $language ) ) {
                                        $translation = $candidate;
                                        break;
                                    }
                                }
                            }

                            // Último recurso: cualquier traducción disponible.
                            if ( empty( $translation ) && ! empty( $translations ) ) {
                                $translation = reset( $translations );
                            }

                            $name = '';

                            if ( isset( $translation['name'] ) ) {
                                $name = $translation['name'];
                            } elseif ( isset( $item['name'] ) ) {
                                $name = $item['name'];
                            }

                            $description = isset( $translation['description'] )
                                ? wp_strip_all_tags( html_entity_decode( $translation['description'], ENT_QUOTES, 'UTF-8' ) )
                                : '';

                            $muscles = array();

                            // Los músculos de wger no siempre tienen traducción española.
                            // Traducimos los nombres habituales y mantenemos el valor original
                            // como fallback para no perder información.
                            $muscle_translations = array(
                                'pectoralis major' => 'pectoral mayor',
                                'pectoralis minor' => 'pectoral menor',
                                'anterior deltoid' => 'deltoides anterior',
                                'posterior deltoid' => 'deltoides posterior',
                                'lateral deltoid' => 'deltoides lateral',
                                'deltoid' => 'deltoides',
                                'biceps brachii' => 'bíceps braquial',
                                'triceps brachii' => 'tríceps braquial',
                                'latissimus dorsi' => 'dorsal ancho',
                                'trapezius' => 'trapecio',
                                'rhomboid' => 'romboides',
                                'rectus abdominis' => 'recto abdominal',
                                'obliquus externus abdominis' => 'oblicuo externo',
                                'obliquus internus abdominis' => 'oblicuo interno',
                                'quadriceps femoris' => 'cuádriceps',
                                'hamstrings' => 'isquiotibiales',
                                'gluteus maximus' => 'glúteo mayor',
                                'gastrocnemius' => 'gemelos',
                                'soleus' => 'sóleo',
                                'erector spinae' => 'erectores espinales',
                                'brachialis' => 'braquial',
                                'brachioradialis' => 'braquiorradial',
                                'adductors' => 'aductores',
                                'abductors' => 'abductores',
                            );

                            if ( isset( $item['muscles'] ) && is_array( $item['muscles'] ) ) {
                                foreach ( $item['muscles'] as $muscle ) {
                                    $muscle_name = '';

                                    if ( isset( $muscle['name_en'] ) && '' !== $muscle['name_en'] ) {
                                        $muscle_name = $muscle['name_en'];
                                    } elseif ( isset( $muscle['name'] ) && '' !== $muscle['name'] ) {
                                        $muscle_name = $muscle['name'];
                                    }

                                    if ( '' !== $muscle_name ) {
                                        $muscle_key = strtolower( trim( wp_strip_all_tags( $muscle_name ) ) );
                                        $muscles[] = isset( $muscle_translations[ $muscle_key ] )
                                            ? $muscle_translations[ $muscle_key ]
                                            : $muscle_name;
                                    }
                                }
                            }

                            $equipment = array();

                            $equipment_translations = array(
                                'barbell' => 'barra',
                                'dumbbell' => 'mancuerna',
                                'dumbbells' => 'mancuernas',
                                'cable' => 'polea',
                                'machine' => 'máquina',
                                'bench' => 'banco',
                                'pull-up bar' => 'barra de dominadas',
                                'ez curl bar' => 'barra Z',
                                'kettlebell' => 'kettlebell',
                                'resistance band' => 'banda elástica',
                                'bodyweight' => 'peso corporal',
                                'none (bodyweight exercise)' => 'peso corporal',
                            );

                            if ( isset( $item['equipment'] ) && is_array( $item['equipment'] ) ) {
                                foreach ( $item['equipment'] as $equipment_item ) {
                                    if ( isset( $equipment_item['name'] ) && '' !== $equipment_item['name'] ) {
                                        $equipment_name = $equipment_item['name'];
                                        $equipment_key  = strtolower( trim( wp_strip_all_tags( $equipment_name ) ) );
                                        $equipment[]    = isset( $equipment_translations[ $equipment_key ] )
                                            ? $equipment_translations[ $equipment_key ]
                                            : $equipment_name;
                                    }
                                }
                            }

                            $category = '';

                            if ( isset( $item['category']['name'] ) ) {
                                $category = $item['category']['name'];

                                $category_translations = array(
                                    'arms' => 'brazos',
                                    'legs' => 'piernas',
                                    'chest' => 'pecho',
                                    'back' => 'espalda',
                                    'shoulders' => 'hombros',
                                    'abs' => 'abdominales',
                                    'calves' => 'gemelos',
                                    'cardio' => 'cardio',
                                );

                                $category_key = strtolower( trim( wp_strip_all_tags( $category ) ) );

                                if ( isset( $category_translations[ $category_key ] ) ) {
                                    $category = $category_translations[ $category_key ];
                                }
                            }

                            $images = array();

                            if ( isset( $item['images'] ) && is_array( $item['images'] ) ) {
                                foreach ( $item['images'] as $image ) {
                                    if ( isset( $image['image'] ) && '' !== $image['image'] ) {
                                        $images[] = esc_url_raw( $image['image'] );
                                    }
                                }
                            }

                            if ( '' !== $name ) {
                                $results[] = array(
                                    'id'          => isset( $item['id'] ) ? absint( $item['id'] ) : 0,
                                    'name'        => $name,
                                    'category'    => $category,
                                    'muscles'     => $muscles,
                                    'equipment'   => $equipment,
                                    'description' => $description,
                                    'image'       => ! empty( $images ) ? $images[0] : '',
                                );
                            }
                        }

                        set_transient( $cache_key, $results, 10 * MINUTE_IN_SECONDS );
                    }
                }
            }
        }
    }

    ?>

    <div class="wrap">

        <h1>GymTrack - Biblioteca externa</h1>

        <p>
            Esta sección demuestra la integración de GymTrack con una API externa real.
            Puedes buscar ejercicios en la biblioteca pública de <strong>wger</strong> y consultar
            información adicional sin modificar los datos locales de GymTrack.
        </p>

        <p>
            <a href="https://wger.de/" target="_blank" rel="noopener noreferrer">
                Ver proyecto wger
            </a>
        </p>

        <form method="get" style="margin:25px 0;">

            <input type="hidden" name="page" value="gymtrack-external-api">

            <input
                type="text"
                name="gymtrack_external_term"
                value="<?php echo esc_attr( $search ); ?>"
                placeholder="Ejemplo: bench press"
                style="width:350px; max-width:100%;"
            >

            <button
                type="submit"
                name="gymtrack_external_search"
                value="1"
                class="button button-primary"
            >
                Buscar ejercicio
            </button>

        </form>

        <?php if ( '' !== $error ) : ?>

            <div class="notice notice-error"><p><?php echo esc_html( $error ); ?></p></div>

        <?php elseif ( $searched && empty( $results ) ) : ?>

            <div class="notice notice-warning"><p>No se han encontrado ejercicios para esa búsqueda.</p></div>

        <?php elseif ( ! empty( $results ) ) : ?>

            <h2>Resultados externos</h2>

            <div style="display:grid; grid-template-columns:repeat(auto-fit,minmax(320px,1fr)); gap:20px; margin-top:20px;">

                <?php foreach ( $results as $result ) : ?>

                    <div style="background:#fff; border:1px solid #ccd0d4; padding:20px; box-sizing:border-box;">

                        <?php if ( ! empty( $result['image'] ) ) : ?>
                            <img
                                src="<?php echo esc_url( $result['image'] ); ?>"
                                alt="<?php echo esc_attr( $result['name'] ); ?>"
                                style="width:100%; max-height:220px; object-fit:contain; margin-bottom:15px;"
                            >
                        <?php endif; ?>

                        <h3 style="margin-top:0;">
                            <?php echo esc_html( $result['name'] ); ?>
                        </h3>

                        <?php if ( '' !== $result['category'] ) : ?>
                            <p><strong>Categoría:</strong> <?php echo esc_html( $result['category'] ); ?></p>
                        <?php endif; ?>

                        <?php if ( ! empty( $result['muscles'] ) ) : ?>
                            <p><strong>Músculos:</strong> <?php echo esc_html( implode( ', ', $result['muscles'] ) ); ?></p>
                        <?php endif; ?>

                        <?php if ( ! empty( $result['equipment'] ) ) : ?>
                            <p><strong>Equipamiento:</strong> <?php echo esc_html( implode( ', ', $result['equipment'] ) ); ?></p>
                        <?php endif; ?>

                        <?php if ( '' !== $result['description'] ) : ?>
                            <p>
                                <strong>Descripción:</strong><br>
                                <?php echo esc_html( wp_trim_words( $result['description'], 80, '...' ) ); ?>
                            </p>
                        <?php endif; ?>

                        <?php if ( ! empty( $result['id'] ) ) : ?>
                            <p style="color:#646970;">ID externo: <?php echo esc_html( $result['id'] ); ?></p>
                        <?php endif; ?>

                    </div>

                <?php endforeach; ?>

            </div>

        <?php endif; ?>

    </div>

    <?php
}


/**
 * =========================================================
 * PÁGINA DE EJERCICIOS
 * =========================================================
 */

function gymtrack_exercises_page() {

    global $wpdb;

    $table_exercises = $wpdb->prefix . 'gymtrack_exercises';
    $table_sets = $wpdb->prefix . 'gymtrack_sets';
    $table_routine_exercises = $wpdb->prefix . 'gymtrack_routine_exercises';

    if ( ! gymtrack_can_manage() ) {
        return;
    }

    /*
     * Reparar textos antiguos que quedaron almacenados con caracteres
     * españoles corruptos. Solo se corrigen coincidencias exactas conocidas.
     */
    $exercise_text_repairs = array(
        array( 'Curl con barra para trabajar los bíceps.', 'Curl con barra para trabajar los b??ceps.' ),
        array( 'Sentadilla para trabajar principalmente cuádriceps y glúteos.', 'Sentadilla para trabajar principalmente cu??driceps y gl??teos.' ),
    );

    foreach ( $exercise_text_repairs as $repair ) {
        $wpdb->query(
            $wpdb->prepare(
                "UPDATE $table_exercises SET description = %s WHERE description = %s",
                $repair[0],
                $repair[1]
            )
        );
    }

    /*
     * Editar ejercicio.
     */
    if ( isset( $_POST['gymtrack_edit_exercise'] ) ) {

        $exercise_id = absint( isset( $_POST['exercise_id'] ) ? $_POST['exercise_id'] : 0 );

        check_admin_referer( 'gymtrack_edit_exercise_' . $exercise_id );

        $name = sanitize_text_field(
            isset( $_POST['name'] ) ? wp_unslash( $_POST['name'] ) : ''
        );
        $muscle_group = sanitize_text_field(
            isset( $_POST['muscle_group'] ) ? wp_unslash( $_POST['muscle_group'] ) : ''
        );
        $description = sanitize_textarea_field(
            isset( $_POST['description'] ) ? wp_unslash( $_POST['description'] ) : ''
        );

        if ( $exercise_id > 0 && '' !== $name && '' !== $muscle_group ) {
            $updated = $wpdb->update(
                $table_exercises,
                array(
                    'name'         => $name,
                    'muscle_group' => $muscle_group,
                    'description'  => $description,
                ),
                array( 'id' => $exercise_id ),
                array( '%s', '%s', '%s' ),
                array( '%d' )
            );

            if ( false !== $updated ) {
                wp_safe_redirect( admin_url( 'admin.php?page=gymtrack' ) );
                exit;
            }
        }

        echo '<div class="notice notice-error is-dismissible"><p>El nombre y el grupo muscular son obligatorios.</p></div>';
    }

    /*
     * Eliminar ejercicio.
     * No permitimos borrar ejercicios que ya estén utilizados en rutinas
     * o entrenamientos, para evitar registros huérfanos.
     */
    if (
        isset( $_GET['action'] ) &&
        'delete' === $_GET['action'] &&
        isset( $_GET['exercise_id'] )
    ) {

        $exercise_id = absint( $_GET['exercise_id'] );

        check_admin_referer( 'gymtrack_delete_exercise_' . $exercise_id );

        $used_in_routines = (int) $wpdb->get_var(
            $wpdb->prepare(
                "SELECT COUNT(*) FROM $table_routine_exercises WHERE exercise_id = %d",
                $exercise_id
            )
        );

        $used_in_workouts = (int) $wpdb->get_var(
            $wpdb->prepare(
                "SELECT COUNT(*) FROM $table_sets WHERE exercise_id = %d",
                $exercise_id
            )
        );

        if ( $used_in_routines > 0 || $used_in_workouts > 0 ) {
            echo '<div class="notice notice-error is-dismissible"><p>No se puede eliminar este ejercicio porque está siendo utilizado en rutinas o entrenamientos registrados.</p></div>';
        } else {
            $deleted = $wpdb->delete(
                $table_exercises,
                array( 'id' => $exercise_id ),
                array( '%d' )
            );

            if ( false !== $deleted ) {
                echo '<div class="notice notice-success is-dismissible"><p>Ejercicio eliminado correctamente.</p></div>';
            } else {
                echo '<div class="notice notice-error is-dismissible"><p>No se ha podido eliminar el ejercicio.</p></div>';
            }
        }
    }

    /*
     * Añadir ejercicio.
     */
    if (
        isset( $_POST['gymtrack_add_exercise'] ) &&
        check_admin_referer( 'gymtrack_add_exercise' )
    ) {

        $name = sanitize_text_field(
            isset( $_POST['name'] ) ? wp_unslash( $_POST['name'] ) : ''
        );
        $muscle_group = sanitize_text_field(
            isset( $_POST['muscle_group'] ) ? wp_unslash( $_POST['muscle_group'] ) : ''
        );
        $description = sanitize_textarea_field(
            isset( $_POST['description'] ) ? wp_unslash( $_POST['description'] ) : ''
        );

        if ( '' !== $name && '' !== $muscle_group ) {
            $duplicate = $wpdb->get_var(
                $wpdb->prepare(
                    "SELECT id FROM $table_exercises WHERE name = %s LIMIT 1",
                    $name
                )
            );

            if ( $duplicate ) {
                echo '<div class="notice notice-error is-dismissible"><p>Ya existe un ejercicio con ese nombre.</p></div>';
            } else {
                $inserted = $wpdb->insert(
                    $table_exercises,
                    array(
                        'name'         => $name,
                        'muscle_group' => $muscle_group,
                        'description'  => $description,
                    ),
                    array( '%s', '%s', '%s' )
                );

                if ( false !== $inserted ) {
                    wp_safe_redirect( admin_url( 'admin.php?page=gymtrack' ) );
                    exit;
                }

                echo '<div class="notice notice-error is-dismissible"><p>No se ha podido guardar el ejercicio.</p></div>';
            }
        } else {
            echo '<div class="notice notice-error is-dismissible"><p>El nombre y el grupo muscular son obligatorios.</p></div>';
        }
    }

    $exercises = $wpdb->get_results(
        "SELECT * FROM $table_exercises ORDER BY name ASC"
    );
    ?>

    <div class="wrap">

        <h1>GymTrack - Ejercicios</h1>

        <h2>Añadir ejercicio</h2>

        <form method="post">
            <?php wp_nonce_field( 'gymtrack_add_exercise' ); ?>

            <table class="form-table">
                <tr>
                    <th><label for="gymtrack-exercise-name">Nombre</label></th>
                    <td><input type="text" name="name" id="gymtrack-exercise-name" class="regular-text" required></td>
                </tr>
                <tr>
                    <th><label for="gymtrack-exercise-muscle">Grupo muscular</label></th>
                    <td><input type="text" name="muscle_group" id="gymtrack-exercise-muscle" class="regular-text" required></td>
                </tr>
                <tr>
                    <th><label for="gymtrack-exercise-description">Descripción</label></th>
                    <td><textarea name="description" id="gymtrack-exercise-description" rows="4" class="large-text"></textarea></td>
                </tr>
            </table>

            <p>
                <button type="submit" name="gymtrack_add_exercise" class="button button-primary">Añadir ejercicio</button>
            </p>
        </form>

        <hr>

        <h2>Ejercicios registrados</h2>

        <?php if ( empty( $exercises ) ) : ?>
            <p>No hay ejercicios registrados todavía.</p>
        <?php else : ?>
            <table class="widefat striped">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Nombre</th>
                        <th>Grupo muscular</th>
                        <th>Descripción</th>
                        <th>Acciones</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ( $exercises as $exercise ) : ?>
                        <tr>
                            <td><?php echo esc_html( $exercise->id ); ?></td>
                            <td><strong><?php echo esc_html( $exercise->name ); ?></strong></td>
                            <td><?php echo esc_html( $exercise->muscle_group ); ?></td>
                            <td><?php echo esc_html( $exercise->description ); ?></td>
                            <td>
                                <button
                                    type="button"
                                    class="button gymtrack-toggle-exercise-edit"
                                    aria-expanded="false"
                                    aria-controls="gymtrack-exercise-edit-<?php echo esc_attr( $exercise->id ); ?>"
                                >
                                    Editar
                                </button>

                                <?php
                                $delete_url = wp_nonce_url(
                                    admin_url(
                                        'admin.php?page=gymtrack&action=delete&exercise_id=' . $exercise->id
                                    ),
                                    'gymtrack_delete_exercise_' . $exercise->id
                                );
                                ?>

                                <a
                                    href="<?php echo esc_url( $delete_url ); ?>"
                                    class="button"
                                    onclick="return confirm('¿Seguro que quieres eliminar este ejercicio? Si está siendo utilizado, GymTrack impedirá el borrado.');"
                                >
                                    Eliminar
                                </a>

                                <div
                                    id="gymtrack-exercise-edit-<?php echo esc_attr( $exercise->id ); ?>"
                                    hidden
                                    style="margin-top:12px;padding:12px;border-left:3px solid #2271b1;background:#f6f7f7;max-width:700px;"
                                >
                                    <form method="post">
                                        <?php wp_nonce_field( 'gymtrack_edit_exercise_' . $exercise->id ); ?>
                                        <input type="hidden" name="exercise_id" value="<?php echo esc_attr( $exercise->id ); ?>">

                                        <p>
                                            <label for="gymtrack-edit-name-<?php echo esc_attr( $exercise->id ); ?>"><strong>Nombre</strong></label><br>
                                            <input type="text" name="name" id="gymtrack-edit-name-<?php echo esc_attr( $exercise->id ); ?>" class="regular-text" value="<?php echo esc_attr( $exercise->name ); ?>" required>
                                        </p>

                                        <p>
                                            <label for="gymtrack-edit-muscle-<?php echo esc_attr( $exercise->id ); ?>"><strong>Grupo muscular</strong></label><br>
                                            <input type="text" name="muscle_group" id="gymtrack-edit-muscle-<?php echo esc_attr( $exercise->id ); ?>" class="regular-text" value="<?php echo esc_attr( $exercise->muscle_group ); ?>" required>
                                        </p>

                                        <p>
                                            <label for="gymtrack-edit-description-<?php echo esc_attr( $exercise->id ); ?>"><strong>Descripción</strong></label><br>
                                            <textarea name="description" id="gymtrack-edit-description-<?php echo esc_attr( $exercise->id ); ?>" rows="3" class="large-text"><?php echo esc_textarea( $exercise->description ); ?></textarea>
                                        </p>

                                        <p>
                                            <button type="submit" name="gymtrack_edit_exercise" class="button button-primary">Guardar cambios</button>
                                        </p>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>

    </div>

    <script>
    (function () {
        document.querySelectorAll('.gymtrack-toggle-exercise-edit').forEach(function (button) {
            button.addEventListener('click', function () {
                var panelId = button.getAttribute('aria-controls');
                var panel = document.getElementById(panelId);
                if (!panel) return;
                var willOpen = panel.hidden;
                panel.hidden = !willOpen;
                button.setAttribute('aria-expanded', willOpen ? 'true' : 'false');
                button.textContent = willOpen ? 'Ocultar edición' : 'Editar';
            });
        });
    }());
    </script>

    <?php
}


/**
 * =========================================================
 * PÁGINA DE RUTINAS
 * =========================================================
 */

function gymtrack_routine_display_text( $text ) {

    return str_replace(
        array(
            'b??ceps',
            'B??ceps',
            't??cnica',
            'T??cnica',
            'tecnica',
            'Tecnica',
            'Mantener buena tecnica',
            'Mantener buena Tecnica',
        ),
        array(
            'bíceps',
            'Bíceps',
            'técnica',
            'Técnica',
            'técnica',
            'Técnica',
            'Mantener buena técnica',
            'Mantener buena Técnica',
        ),
        (string) $text
    );
}

function gymtrack_routines_page() {

    global $wpdb;

    $table_routines = $wpdb->prefix . 'gymtrack_routines';
    $table_exercises = $wpdb->prefix . 'gymtrack_exercises';
    $table_routine_exercises = $wpdb->prefix . 'gymtrack_routine_exercises';

    if ( ! gymtrack_can_manage() ) {
        return;
    }

    /*
     * Reparación exacta de textos antiguos que pudieron quedar guardados
     * con caracteres españoles corruptos. Solo se modifican coincidencias
     * exactas y conocidas; no se altera ningún otro dato.
     */
    $wpdb->query( $wpdb->prepare(
        "UPDATE $table_exercises SET name = %s WHERE name = %s",
        'Curl de bíceps',
        'Curl de b??ceps'
    ) );

    $wpdb->query( $wpdb->prepare(
        "UPDATE $table_exercises SET muscle_group = %s WHERE muscle_group = %s",
        'Bíceps',
        'B??ceps'
    ) );

    $wpdb->query( $wpdb->prepare(
        "UPDATE $table_routine_exercises SET notes = %s WHERE notes = %s",
        'Controlar la técnica y mantener 1-2 repeticiones en reserva',
        'Controlar la t??cnica y mantener 1-2 repeticiones en reserva'
    ) );

    $wpdb->query( $wpdb->prepare(
        "UPDATE $table_routines SET description = %s WHERE description = %s",
        'Mantener buena técnica',
        'Mantener buena tecnica'
    ) );


    /*
     * Añadir rutina
     */

    if ( isset( $_POST['gymtrack_add_routine'] ) ) {

        check_admin_referer( 'gymtrack_add_routine' );

        $name = sanitize_text_field(
            isset( $_POST['routine_name'] ) ? wp_unslash( $_POST['routine_name'] ) : ''
        );

        $description = sanitize_textarea_field(
            isset( $_POST['routine_description'] ) ? wp_unslash( $_POST['routine_description'] ) : ''
        );

        if ( ! empty( $name ) ) {

            $wpdb->insert(
                $table_routines,
                array(
                    'name'        => $name,
                    'description' => $description,
                ),
                array(
                    '%s',
                    '%s',
                )
            );

            wp_safe_redirect( admin_url( 'admin.php?page=gymtrack-routines' ) );
            exit;
        }
    }


    /*
     * Editar rutina
     */

    if ( isset( $_POST['gymtrack_edit_routine'] ) ) {

        $routine_id = absint( isset( $_POST['routine_id'] ) ? $_POST['routine_id'] : 0 );

        check_admin_referer(
            'gymtrack_edit_routine_' . $routine_id
        );

        $name = sanitize_text_field(
            isset( $_POST['routine_name'] ) ? wp_unslash( $_POST['routine_name'] ) : ''
        );

        $description = sanitize_textarea_field(
            isset( $_POST['routine_description'] ) ? wp_unslash( $_POST['routine_description'] ) : ''
        );

        if ( $routine_id > 0 && ! empty( $name ) ) {

            $updated = $wpdb->update(
                $table_routines,
                array(
                    'name'        => $name,
                    'description' => $description,
                ),
                array(
                    'id' => $routine_id,
                ),
                array(
                    '%s',
                    '%s',
                ),
                array(
                    '%d',
                )
            );

            if ( false !== $updated ) {
                wp_safe_redirect( admin_url( 'admin.php?page=gymtrack-routines' ) );
                exit;
            }
        }
    }


    /*
     * Añadir ejercicio a rutina
     */

    if ( isset( $_POST['gymtrack_add_routine_exercise'] ) ) {

        $routine_id = absint( isset( $_POST['routine_id'] ) ? $_POST['routine_id'] : 0 );
        $exercise_id = absint( isset( $_POST['exercise_id'] ) ? $_POST['exercise_id'] : 0 );
        $exercise_order = absint( isset( $_POST['exercise_order'] ) ? $_POST['exercise_order'] : 1 );
        $target_sets = absint( isset( $_POST['target_sets'] ) ? $_POST['target_sets'] : 1 );
        $target_repetitions = absint( isset( $_POST['target_repetitions'] ) ? $_POST['target_repetitions'] : 1 );
        $rest_seconds = absint( isset( $_POST['rest_seconds'] ) ? $_POST['rest_seconds'] : 0 );
        $notes = sanitize_textarea_field(
            isset( $_POST['routine_exercise_notes'] ) ? wp_unslash( $_POST['routine_exercise_notes'] ) : ''
        );

        check_admin_referer(
            'gymtrack_add_routine_exercise_' . $routine_id
        );

        if ( $routine_id > 0 && $exercise_id > 0 ) {

            $exercise_order = max( 1, $exercise_order );
            $target_sets = max( 1, $target_sets );
            $target_repetitions = max( 1, $target_repetitions );
            $rest_seconds = max( 0, $rest_seconds );

            $routine_exists = $wpdb->get_var(
                $wpdb->prepare(
                    "SELECT id FROM $table_routines WHERE id = %d LIMIT 1",
                    $routine_id
                )
            );

            $exercise_exists = $wpdb->get_var(
                $wpdb->prepare(
                    "SELECT id FROM $table_exercises WHERE id = %d LIMIT 1",
                    $exercise_id
                )
            );

            if ( $routine_exists && $exercise_exists ) {

                $existing_exercise = $wpdb->get_var(
                    $wpdb->prepare(
                        "SELECT id
                        FROM $table_routine_exercises
                        WHERE routine_id = %d
                        AND exercise_id = %d
                        LIMIT 1",
                        $routine_id,
                        $exercise_id
                    )
                );

                if ( ! $existing_exercise ) {

                    $wpdb->insert(
                        $table_routine_exercises,
                        array(
                            'routine_id'         => $routine_id,
                            'exercise_id'        => $exercise_id,
                            'exercise_order'     => $exercise_order,
                            'target_sets'        => $target_sets,
                            'target_repetitions' => $target_repetitions,
                            'rest_seconds'       => $rest_seconds,
                            'notes'              => $notes,
                        ),
                        array(
                            '%d',
                            '%d',
                            '%d',
                            '%d',
                            '%d',
                            '%d',
                            '%s',
                        )
                    );

                    wp_safe_redirect( admin_url( 'admin.php?page=gymtrack-routines' ) );
                    exit;
                }
            }
        }
    }


    /*
     * Editar ejercicio de rutina
     */

    if ( isset( $_POST['gymtrack_edit_routine_exercise'] ) ) {

        $routine_exercise_id = absint( isset( $_POST['routine_exercise_id'] ) ? $_POST['routine_exercise_id'] : 0 );
        $routine_id = absint( isset( $_POST['routine_id'] ) ? $_POST['routine_id'] : 0 );

        check_admin_referer(
            'gymtrack_edit_routine_exercise_' . $routine_exercise_id
        );

        $exercise_order = max( 1, absint( isset( $_POST['exercise_order'] ) ? $_POST['exercise_order'] : 1 ) );
        $target_sets = max( 1, absint( isset( $_POST['target_sets'] ) ? $_POST['target_sets'] : 1 ) );
        $target_repetitions = max( 1, absint( isset( $_POST['target_repetitions'] ) ? $_POST['target_repetitions'] : 1 ) );
        $rest_seconds = max( 0, absint( isset( $_POST['rest_seconds'] ) ? $_POST['rest_seconds'] : 0 ) );
        $notes = sanitize_textarea_field(
            isset( $_POST['routine_exercise_notes'] ) ? wp_unslash( $_POST['routine_exercise_notes'] ) : ''
        );

        if ( $routine_exercise_id > 0 ) {

            $where = array( 'id' => $routine_exercise_id );
            $where_format = array( '%d' );

            if ( $routine_id > 0 ) {
                $where['routine_id'] = $routine_id;
                $where_format[] = '%d';
            }

            $updated = $wpdb->update(
                $table_routine_exercises,
                array(
                    'exercise_order'     => $exercise_order,
                    'target_sets'        => $target_sets,
                    'target_repetitions' => $target_repetitions,
                    'rest_seconds'       => $rest_seconds,
                    'notes'              => $notes,
                ),
                $where,
                array(
                    '%d',
                    '%d',
                    '%d',
                    '%d',
                    '%s',
                ),
                $where_format
            );

            if ( false !== $updated ) {
                wp_safe_redirect( admin_url( 'admin.php?page=gymtrack-routines' ) );
                exit;
            }
        }
    }


    /*
     * Eliminar ejercicio de rutina
     */

    if ( isset( $_GET['delete_routine_exercise'] ) ) {

        $routine_exercise_id =
            absint( $_GET['delete_routine_exercise'] );

        if ( $routine_exercise_id > 0 ) {

            check_admin_referer(
                'gymtrack_delete_routine_exercise_' . $routine_exercise_id
            );

            $table_routine_exercises = $wpdb->prefix . 'gymtrack_routine_exercises';

            $wpdb->delete(
                $table_routine_exercises,
                array(
                    'id' => $routine_exercise_id,
                ),
                array(
                    '%d',
                )
            );

            wp_safe_redirect( admin_url( 'admin.php?page=gymtrack-routines' ) );
            exit;
        }
    }


    /*
     * Eliminar rutina
     *
     * También eliminamos sus relaciones.
     */

    if ( isset( $_GET['delete_routine'] ) ) {

        $routine_id = absint( $_GET['delete_routine'] );

        if ( $routine_id > 0 ) {

            check_admin_referer(
                'gymtrack_delete_routine_' . $routine_id
            );

            $table_routine_exercises = $wpdb->prefix . 'gymtrack_routine_exercises';


            $wpdb->delete(
                $table_routine_exercises,
                array(
                    'routine_id' => $routine_id,
                ),
                array(
                    '%d',
                )
            );


            $wpdb->delete(
                $table_routines,
                array(
                    'id' => $routine_id,
                ),
                array(
                    '%d',
                )
            );

            wp_safe_redirect( admin_url( 'admin.php?page=gymtrack-routines' ) );
            exit;
        }
    }


    /*
     * Obtener rutinas
     */

    $routines = $wpdb->get_results(
        "SELECT *
        FROM $table_routines
        ORDER BY id DESC"
    );
    ?>

    <div class="wrap">

        <h1>GymTrack - Rutinas</h1>

        <p>
            Crea y gestiona las rutinas de entrenamiento.
        </p>

        <h2>Añadir rutina</h2>

        <form method="post">

            <?php wp_nonce_field( 'gymtrack_add_routine' ); ?>

            <table class="form-table">

                <tr>

                    <th>
                        <label for="routine_name">
                            Nombre
                        </label>
                    </th>

                    <td>
                        <input
                            type="text"
                            name="routine_name"
                            id="routine_name"
                            class="regular-text"
                            required
                        >
                    </td>

                </tr>

                <tr>

                    <th>
                        <label for="routine_description">
                            Descripción
                        </label>
                    </th>

                    <td>

                        <textarea
                            name="routine_description"
                            id="routine_description"
                            rows="4"
                            class="large-text"
                        ></textarea>

                    </td>

                </tr>

            </table>

            <p>

                <input
                    type="submit"
                    name="gymtrack_add_routine"
                    class="button button-primary"
                    value="Añadir rutina"
                >

            </p>

        </form>

        <hr>

        <h2>Rutinas existentes</h2>

        <?php if ( empty( $routines ) ) : ?>

            <div class="notice notice-info">

                <p>
                    Todavía no hay rutinas creadas.
                </p>

            </div>

        <?php else : ?>

            <?php foreach ( $routines as $routine ) : ?>

                <?php

                // Tablas ya definidas al inicio de la página.


                /*
                 * Ejercicios de la rutina
                 */

                $routine_exercises = $wpdb->get_results(
                    $wpdb->prepare(
                        "SELECT
                            re.id,
                            re.exercise_order,
                            re.target_sets,
                            re.target_repetitions,
                            re.rest_seconds,
                            re.notes,
                            e.name,
                            e.muscle_group
                        FROM $table_routine_exercises re
                        INNER JOIN $table_exercises e
                            ON re.exercise_id = e.id
                        WHERE re.routine_id = %d
                        ORDER BY re.exercise_order ASC, re.id ASC",
                        $routine->id
                    )
                );


                /*
                 * Ejercicios disponibles
                 */

                $available_exercises = $wpdb->get_results(
                    $wpdb->prepare(
                        "SELECT
                            id,
                            name,
                            muscle_group
                        FROM $table_exercises
                        WHERE id NOT IN (
                            SELECT exercise_id
                            FROM $table_routine_exercises
                            WHERE routine_id = %d
                        )
                        ORDER BY name ASC",
                        $routine->id
                    )
                );

                ?>

                <div class="postbox">

                    <div class="inside">

                        <h3>
                            <?php echo esc_html( gymtrack_routine_display_text( $routine->name ) ); ?>
                        </h3>

                        <p>
                            <?php echo esc_html( gymtrack_routine_display_text( $routine->description ) ); ?>
                        </p>

                        <div class="gymtrack-edit-section" style="margin:15px 0;">
                            <button
                                type="button"
                                class="button gymtrack-toggle-edit"
                                aria-expanded="false"
                                aria-controls="gymtrack-edit-routine-<?php echo esc_attr( $routine->id ); ?>"
                            >
                                Editar rutina
                            </button>

                            <div
                                id="gymtrack-edit-routine-<?php echo esc_attr( $routine->id ); ?>"
                                class="gymtrack-edit-panel"
                                hidden
                                style="margin-top:15px;"
                            >

                            <form method="post" style="margin-top:15px;">

                                <?php
                                wp_nonce_field(
                                    'gymtrack_edit_routine_' . $routine->id
                                );
                                ?>

                                <input
                                    type="hidden"
                                    name="routine_id"
                                    value="<?php echo esc_attr( $routine->id ); ?>"
                                >

                                <p>
                                    <label for="routine_name_<?php echo esc_attr( $routine->id ); ?>">
                                        <strong>Nombre</strong>
                                    </label>
                                    <br>
                                    <input
                                        type="text"
                                        name="routine_name"
                                        id="routine_name_<?php echo esc_attr( $routine->id ); ?>"
                                        class="regular-text"
                                        value="<?php echo esc_attr( gymtrack_routine_display_text( $routine->name ) ); ?>"
                                        required
                                    >
                                </p>

                                <p>
                                    <label for="routine_description_<?php echo esc_attr( $routine->id ); ?>">
                                        <strong>Descripción</strong>
                                    </label>
                                    <br>
                                    <textarea
                                        name="routine_description"
                                        id="routine_description_<?php echo esc_attr( $routine->id ); ?>"
                                        rows="3"
                                        class="large-text"
                                    ><?php echo esc_textarea( gymtrack_routine_display_text( $routine->description ) ); ?></textarea>
                                </p>

                                <p>
                                    <input
                                        type="submit"
                                        name="gymtrack_edit_routine"
                                        class="button button-primary"
                                        value="Guardar cambios"
                                    >
                                </p>

                            </form>
                        </div>
                        </div>

                        <h4>Ejercicios de la rutina</h4>

                        <?php if ( empty( $routine_exercises ) ) : ?>

                            <p>
                                Esta rutina todavía no tiene ejercicios.
                            </p>

                        <?php else : ?>

                            <ol>

                                <?php foreach ( $routine_exercises as $routine_exercise ) : ?>

                                    <li>

                                        <strong>
                                            <?php
                                            echo esc_html(
                                                gymtrack_routine_display_text( $routine_exercise->name )
                                            );
                                            ?>
                                        </strong>

                                        —
                                        <?php
                                        echo esc_html(
                                            gymtrack_routine_display_text( $routine_exercise->muscle_group )
                                        );
                                        ?>

                                        <br>

                                        <strong>Orden:</strong>
                                        <?php
                                        echo esc_html(
                                            $routine_exercise->exercise_order
                                        );
                                        ?>

                                        |

                                        <strong>Series:</strong>
                                        <?php
                                        echo esc_html(
                                            $routine_exercise->target_sets
                                        );
                                        ?>

                                        |

                                        <strong>Repeticiones:</strong>
                                        <?php
                                        echo esc_html(
                                            $routine_exercise->target_repetitions
                                        );
                                        ?>

                                        |

                                        <strong>Descanso:</strong>
                                        <?php
                                        echo esc_html(
                                            $routine_exercise->rest_seconds
                                        );
                                        ?>
                                        segundos

                                        <?php if ( ! empty( $routine_exercise->notes ) ) : ?>

                                            <br>

                                            <strong>Notas:</strong>
                                            <?php
                                            echo esc_html(
                                                gymtrack_routine_display_text( $routine_exercise->notes )
                                            );
                                            ?>

                                        <?php endif; ?>

                                        <?php

                                        $delete_exercise_url = wp_nonce_url(
                                            admin_url(
                                                'admin.php?page=gymtrack-routines&delete_routine_exercise=' . $routine_exercise->id
                                            ),
                                            'gymtrack_delete_routine_exercise_' . $routine_exercise->id
                                        );

                                        ?>

                                        <div class="gymtrack-edit-section" style="margin:10px 0;">
                                            <button
                                                type="button"
                                                class="button gymtrack-toggle-edit"
                                                aria-expanded="false"
                                                aria-controls="gymtrack-edit-exercise-<?php echo esc_attr( $routine_exercise->id ); ?>"
                                            >
                                                Editar ejercicio
                                            </button>

                                            <div
                                                id="gymtrack-edit-exercise-<?php echo esc_attr( $routine_exercise->id ); ?>"
                                                class="gymtrack-edit-panel"
                                                hidden
                                                style="margin-top:15px;"
                                            >

                                            <form method="post" style="margin-top:15px;">

                                                <?php
                                                wp_nonce_field(
                                                    'gymtrack_edit_routine_exercise_' . $routine_exercise->id
                                                );
                                                ?>

                                                <input
                                                    type="hidden"
                                                    name="routine_id"
                                                    value="<?php echo esc_attr( $routine->id ); ?>"
                                                >

                                                <input
                                                    type="hidden"
                                                    name="routine_exercise_id"
                                                    value="<?php echo esc_attr( $routine_exercise->id ); ?>"
                                                >

                                                <p>
                                                    <label><strong>Orden</strong></label><br>
                                                    <input type="number" name="exercise_order" value="<?php echo esc_attr( $routine_exercise->exercise_order ); ?>" min="1" required style="width:80px;">
                                                </p>

                                                <p>
                                                    <label><strong>Series objetivo</strong></label><br>
                                                    <input type="number" name="target_sets" value="<?php echo esc_attr( $routine_exercise->target_sets ); ?>" min="1" required style="width:80px;">
                                                </p>

                                                <p>
                                                    <label><strong>Repeticiones objetivo</strong></label><br>
                                                    <input type="number" name="target_repetitions" value="<?php echo esc_attr( $routine_exercise->target_repetitions ); ?>" min="1" required style="width:80px;">
                                                </p>

                                                <p>
                                                    <label><strong>Descanso (segundos)</strong></label><br>
                                                    <input type="number" name="rest_seconds" value="<?php echo esc_attr( $routine_exercise->rest_seconds ); ?>" min="0" required style="width:100px;">
                                                </p>

                                                <p>
                                                    <label><strong>Notas</strong></label><br>
                                                    <textarea name="routine_exercise_notes" rows="3" class="large-text"><?php echo esc_textarea( $routine_exercise->notes ); ?></textarea>
                                                </p>

                                                <p>
                                                    <input
                                                        type="submit"
                                                        name="gymtrack_edit_routine_exercise"
                                                        class="button button-primary"
                                                        value="Guardar cambios"
                                                    >
                                                </p>

                                            </form>
                                        </div>
                                        </div>

                                        <br>

                                        <a
                                            href="<?php echo esc_url( $delete_exercise_url ); ?>"
                                            class="button"
                                            onclick="return confirm('¿Seguro que quieres quitar este ejercicio de la rutina?');"
                                        >
                                            Eliminar
                                        </a>

                                    </li>

                                <?php endforeach; ?>

                            </ol>

                        <?php endif; ?>

                        <hr>

                        <h4>Añadir ejercicio</h4>

                        <?php if ( empty( $available_exercises ) ) : ?>

                            <p>
                                No hay ejercicios disponibles.
                            </p>

                        <?php else : ?>

                            <form method="post">

                                <?php
                                wp_nonce_field(
                                    'gymtrack_add_routine_exercise_' . $routine->id
                                );
                                ?>

                                <input
                                    type="hidden"
                                    name="routine_id"
                                    value="<?php echo esc_attr( $routine->id ); ?>"
                                >

                                <p>

                                    <label for="exercise_id_<?php echo esc_attr( $routine->id ); ?>">

                                        <strong>
                                            Ejercicio
                                        </strong>

                                    </label>

                                </p>

                                <select
                                    name="exercise_id"
                                    id="exercise_id_<?php echo esc_attr( $routine->id ); ?>"
                                    required
                                >

                                    <option value="">
                                        Selecciona un ejercicio
                                    </option>

                                    <?php foreach ( $available_exercises as $exercise ) : ?>

                                        <option
                                            value="<?php echo esc_attr( $exercise->id ); ?>"
                                        >

                                            <?php

                                            echo esc_html(
                                                $exercise->name .
                                                ' — ' .
                                                $exercise->muscle_group
                                            );

                                            ?>

                                        </option>

                                    <?php endforeach; ?>

                                </select>

                                <p>

                                    <label for="exercise_order_<?php echo esc_attr( $routine->id ); ?>">

                                        <strong>
                                            Orden
                                        </strong>

                                    </label>

                                    <br>

                                    <input
                                        type="number"
                                        name="exercise_order"
                                        id="exercise_order_<?php echo esc_attr( $routine->id ); ?>"
                                        value="1"
                                        min="1"
                                        style="width:80px;"
                                    >

                                </p>

                                <p>

                                    <label for="target_sets_<?php echo esc_attr( $routine->id ); ?>">

                                        <strong>
                                            Series objetivo
                                        </strong>

                                    </label>

                                    <br>

                                    <input
                                        type="number"
                                        name="target_sets"
                                        id="target_sets_<?php echo esc_attr( $routine->id ); ?>"
                                        value="3"
                                        min="1"
                                        required
                                        style="width:80px;"
                                    >

                                </p>

                                <p>

                                    <label for="target_repetitions_<?php echo esc_attr( $routine->id ); ?>">

                                        <strong>
                                            Repeticiones objetivo
                                        </strong>

                                    </label>

                                    <br>

                                    <input
                                        type="number"
                                        name="target_repetitions"
                                        id="target_repetitions_<?php echo esc_attr( $routine->id ); ?>"
                                        value="10"
                                        min="1"
                                        required
                                        style="width:80px;"
                                    >

                                </p>

                                <p>

                                    <label for="rest_seconds_<?php echo esc_attr( $routine->id ); ?>">

                                        <strong>
                                            Descanso (segundos)
                                        </strong>

                                    </label>

                                    <br>

                                    <input
                                        type="number"
                                        name="rest_seconds"
                                        id="rest_seconds_<?php echo esc_attr( $routine->id ); ?>"
                                        value="90"
                                        min="0"
                                        required
                                        style="width:100px;"
                                    >

                                </p>

                                <p>

                                    <label for="routine_exercise_notes_<?php echo esc_attr( $routine->id ); ?>">

                                        <strong>
                                            Notas
                                        </strong>

                                    </label>

                                    <br>

                                    <textarea
                                        name="routine_exercise_notes"
                                        id="routine_exercise_notes_<?php echo esc_attr( $routine->id ); ?>"
                                        rows="3"
                                        class="large-text"
                                    ></textarea>

                                </p>

                                <p>

                                    <input
                                        type="submit"
                                        name="gymtrack_add_routine_exercise"
                                        class="button button-primary"
                                        value="Añadir ejercicio"
                                    >

                                </p>

                            </form>

                        <?php endif; ?>

                        <p>

                            <?php

                            $delete_url = wp_nonce_url(
                                admin_url(
                                    'admin.php?page=gymtrack-routines&delete_routine=' . $routine->id
                                ),
                                'gymtrack_delete_routine_' . $routine->id
                            );

                            ?>

                            <a
                                href="<?php echo esc_url( $delete_url ); ?>"
                                class="button"
                                onclick="return confirm('¿Seguro que quieres eliminar esta rutina?');"
                            >
                                Eliminar rutina
                            </a>

                        </p>

                    </div>

                </div>

            <?php endforeach; ?>

        <?php endif; ?>

        <style>
            .gymtrack-edit-section {
                display: block;
            }
            .gymtrack-toggle-edit {
                margin-top: 8px;
            }
            .gymtrack-edit-panel {
                padding: 14px 16px;
                border-left: 3px solid #2271b1;
                background: #f6f7f7;
                max-width: 760px;
            }
            .gymtrack-edit-panel p:last-child {
                margin-bottom: 0;
            }
            .gymtrack-routine-exercise-actions {
                margin-top: 12px;
            }
            .gymtrack-add-exercise-form {
                max-width: 760px;
            }
        </style>

        <script>
        document.addEventListener('DOMContentLoaded', function () {
            document.querySelectorAll('.gymtrack-toggle-edit').forEach(function (button) {
                button.addEventListener('click', function () {
                    var panelId = button.getAttribute('aria-controls');
                    var panel = document.getElementById(panelId);
                    if (!panel) {
                        return;
                    }

                    var isHidden = panel.hasAttribute('hidden');

                    if (isHidden) {
                        panel.removeAttribute('hidden');
                        button.setAttribute('aria-expanded', 'true');
                        button.textContent = button.textContent.indexOf('rutina') !== -1
                            ? 'Cerrar edición de rutina'
                            : 'Cerrar edición de ejercicio';
                    } else {
                        panel.setAttribute('hidden', 'hidden');
                        button.setAttribute('aria-expanded', 'false');
                        button.textContent = button.textContent.indexOf('rutina') !== -1
                            ? 'Editar rutina'
                            : 'Editar ejercicio';
                    }
                });
            });
        });
        </script>

    </div>

    <?php
}


/**
 * =========================================================
 * PÁGINA DE ENTRENAMIENTOS
 * =========================================================
 */

function gymtrack_workouts_page() {

    global $wpdb;

    $table_exercises = $wpdb->prefix . 'gymtrack_exercises';
    $table_workouts  = $wpdb->prefix . 'gymtrack_workouts';
    $table_sets      = $wpdb->prefix . 'gymtrack_sets';
    $table_routines  = $wpdb->prefix . 'gymtrack_routines';
    $table_routine_exercises = $wpdb->prefix . 'gymtrack_routine_exercises';


    if ( ! gymtrack_can_use() ) {
        return;
    }


    /*
     * Guardar entrenamiento.
     *
     * Se mantienen dos modos:
     * 1. Registro manual de un ejercicio.
     * 2. Registro a partir de una rutina, con todos sus ejercicios.
     */

    if (
        isset( $_POST['gymtrack_add_workout'] ) &&
        check_admin_referer( 'gymtrack_add_workout' )
    ) {

        $workout_date = sanitize_text_field(
            isset( $_POST['workout_date'] ) ? $_POST['workout_date'] : ''
        );

        $date_object = DateTime::createFromFormat( 'Y-m-d', $workout_date );
        if ( ! $date_object || $date_object->format( 'Y-m-d' ) !== $workout_date ) {
            $workout_date = '';
        }

        $notes = sanitize_textarea_field(
            isset( $_POST['notes'] ) ? $_POST['notes'] : ''
        );

        $current_user_id = get_current_user_id();
        $routine_id = isset( $_POST['routine_id'] ) ? absint( $_POST['routine_id'] ) : 0;
        $workout_rows = array();


        /*
         * Registro mediante rutina.
         */
        if ( $routine_id > 0 && isset( $_POST['routine_exercises'] ) && is_array( $_POST['routine_exercises'] ) ) {

            $routine_exists = $wpdb->get_var(
                $wpdb->prepare(
                    "SELECT id
                    FROM $table_routines
                    WHERE id = %d
                    LIMIT 1",
                    $routine_id
                )
            );

            if ( ! $routine_exists ) {
                echo '<div class="notice notice-error is-dismissible">';
                echo '<p>La rutina seleccionada no existe.</p>';
                echo '</div>';
            } else {

                foreach ( $_POST['routine_exercises'] as $row ) {

                    if ( ! is_array( $row ) ) {
                        continue;
                    }

                    $exercise_id = isset( $row['exercise_id'] ) ? absint( $row['exercise_id'] ) : 0;
                    $weight = isset( $row['weight'] ) ? floatval( $row['weight'] ) : 0;
                    $sets = isset( $row['sets'] ) ? absint( $row['sets'] ) : 0;
                    $repetitions = isset( $row['repetitions'] ) ? absint( $row['repetitions'] ) : 0;
                    $set_notes = isset( $row['notes'] ) ? sanitize_textarea_field( $row['notes'] ) : '';

                    if ( $exercise_id <= 0 || $sets <= 0 || $repetitions <= 0 || $weight <= 0 ) {
                        continue;
                    }

                    $exercise_exists = $wpdb->get_var(
                        $wpdb->prepare(
                            "SELECT id
                            FROM $table_exercises
                            WHERE id = %d
                            LIMIT 1",
                            $exercise_id
                        )
                    );

                    if ( ! $exercise_exists ) {
                        continue;
                    }

                    $workout_rows[] = array(
                        'exercise_id' => $exercise_id,
                        'weight'      => $weight,
                        'sets'        => $sets,
                        'repetitions' => $repetitions,
                        'notes'       => $set_notes,
                    );
                }

                if ( empty( $workout_rows ) || empty( $workout_date ) ) {
                    echo '<div class="notice notice-error is-dismissible">';
                    echo '<p>La fecha y al menos un ejercicio válido son obligatorios.</p>';
                    echo '</div>';
                }
            }

        } else {

            /*
             * Registro manual de un único ejercicio.
             */
            $exercise_id = isset( $_POST['exercise_id'] ) ? absint( $_POST['exercise_id'] ) : 0;
            $weight = isset( $_POST['weight'] ) ? floatval( $_POST['weight'] ) : 0;
            $sets = isset( $_POST['sets'] ) ? absint( $_POST['sets'] ) : 0;
            $repetitions = isset( $_POST['repetitions'] ) ? absint( $_POST['repetitions'] ) : 0;

            if (
                ! empty( $workout_date ) &&
                $exercise_id > 0 &&
                $sets > 0 &&
                $repetitions > 0 &&
                $weight > 0
            ) {

                $exercise_exists = $wpdb->get_var(
                    $wpdb->prepare(
                        "SELECT id
                        FROM $table_exercises
                        WHERE id = %d
                        LIMIT 1",
                        $exercise_id
                    )
                );

                if ( $exercise_exists ) {
                    $workout_rows[] = array(
                        'exercise_id' => $exercise_id,
                        'weight'      => $weight,
                        'sets'        => $sets,
                        'repetitions' => $repetitions,
                        'notes'       => $notes,
                    );
                }
            }

            if ( empty( $workout_rows ) || empty( $workout_date ) ) {
                echo '<div class="notice notice-error is-dismissible">';
                echo '<p>Fecha, ejercicio, peso, series y repeticiones son obligatorios.</p>';
                echo '</div>';
            }
        }


        /*
         * Crear el entrenamiento y sus ejercicios.
         */
        if ( ! empty( $workout_rows ) && ! empty( $workout_date ) ) {

            $wpdb->query( 'START TRANSACTION' );

            $workout_created = $wpdb->insert(
                $table_workouts,
                array(
                    'user_id'      => $current_user_id,
                    'workout_date' => $workout_date,
                    'notes'        => $notes,
                ),
                array(
                    '%d',
                    '%s',
                    '%s',
                )
            );

            $all_sets_created = false;

            if ( $workout_created ) {

                $workout_id = $wpdb->insert_id;
                $all_sets_created = true;

                foreach ( $workout_rows as $workout_row ) {

                    $set_created = $wpdb->insert(
                        $table_sets,
                        array(
                            'workout_id'  => $workout_id,
                            'exercise_id' => $workout_row['exercise_id'],
                            'weight'      => $workout_row['weight'],
                            'sets'        => $workout_row['sets'],
                            'repetitions' => $workout_row['repetitions'],
                            'notes'       => $workout_row['notes'],
                        ),
                        array(
                            '%d',
                            '%d',
                            '%f',
                            '%d',
                            '%d',
                            '%s',
                        )
                    );

                    if ( ! $set_created ) {
                        $all_sets_created = false;
                        break;
                    }
                }
            }

            if ( $workout_created && $all_sets_created ) {

                $wpdb->query( 'COMMIT' );

                echo '<div class="notice notice-success is-dismissible">';
                echo '<p>Entrenamiento registrado correctamente con ' . esc_html( count( $workout_rows ) ) . ' ejercicio(s).</p>';
                echo '</div>';

            } else {

                $wpdb->query( 'ROLLBACK' );

                echo '<div class="notice notice-error is-dismissible">';
                echo '<p>No se ha podido guardar el entrenamiento completo. No se han conservado datos parciales.</p>';
                echo '</div>';
            }
        }
    }


    /*
     * Obtener ejercicios.
     */
    $exercises = $wpdb->get_results(
        "SELECT id, name, muscle_group
        FROM $table_exercises
        ORDER BY name ASC"
    );


    /*
     * Obtener rutinas disponibles para el registro de entrenamientos.
     * Las rutinas son actualmente globales y gestionadas por el administrador.
     */
    $routines = $wpdb->get_results(
        "SELECT id, name, description
        FROM $table_routines
        ORDER BY name ASC"
    );

    $routine_data = array();

    foreach ( $routines as $routine ) {

        $routine_exercises = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT
                    re.exercise_id,
                    re.exercise_order,
                    re.target_sets,
                    re.target_repetitions,
                    re.rest_seconds,
                    re.notes,
                    e.name AS exercise_name,
                    e.muscle_group
                FROM $table_routine_exercises re
                INNER JOIN $table_exercises e
                    ON re.exercise_id = e.id
                WHERE re.routine_id = %d
                ORDER BY re.exercise_order ASC, re.id ASC",
                $routine->id
            ),
            ARRAY_A
        );

        $routine_data[ $routine->id ] = array(
            'id'          => (int) $routine->id,
            'name'        => $routine->name,
            'description' => $routine->description,
            'exercises'   => $routine_exercises,
        );
    }


    /*
     * Obtener entrenamientos.
     */
    if ( gymtrack_is_admin() ) {

        $workouts = $wpdb->get_results(
            "SELECT
                w.id,
                w.workout_date,
                w.notes,
                u.user_login,
                e.name AS exercise_name,
                e.muscle_group,
                s.weight,
                s.sets,
                s.repetitions
            FROM $table_workouts w
            INNER JOIN {$wpdb->users} u
                ON w.user_id = u.ID
            INNER JOIN $table_sets s
                ON w.id = s.workout_id
            INNER JOIN $table_exercises e
                ON s.exercise_id = e.id
            ORDER BY w.workout_date DESC, w.id DESC, s.id ASC"
        );

    } else {

        $current_user_id = get_current_user_id();

        $workouts = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT
                    w.id,
                    w.workout_date,
                    w.notes,
                    u.user_login,
                    e.name AS exercise_name,
                    e.muscle_group,
                    s.weight,
                    s.sets,
                    s.repetitions
                FROM $table_workouts w
                INNER JOIN {$wpdb->users} u
                    ON w.user_id = u.ID
                INNER JOIN $table_sets s
                    ON w.id = s.workout_id
                INNER JOIN $table_exercises e
                    ON s.exercise_id = e.id
                WHERE w.user_id = %d
                ORDER BY w.workout_date DESC, w.id DESC, s.id ASC",
                $current_user_id
            )
        );
    }

    ?>

    <div class="wrap">

        <h1>GymTrack - Entrenamientos</h1>

        <h2>Registrar entrenamiento</h2>

        <p>
            Puedes registrar un ejercicio manualmente o seleccionar una rutina.
            Al usar una rutina se cargarán automáticamente sus ejercicios, series y repeticiones objetivo.
        </p>

        <form method="post" id="gymtrack-workout-form">

            <?php wp_nonce_field( 'gymtrack_add_workout' ); ?>

            <table class="form-table">

                <tr>
                    <th>
                        <label for="workout_date">Fecha</label>
                    </th>
                    <td>
                        <input
                            type="date"
                            name="workout_date"
                            id="workout_date"
                            value="<?php echo esc_attr( current_time( 'Y-m-d' ) ); ?>"
                            required
                        >
                    </td>
                </tr>

                <tr>
                    <th>
                        <label for="routine_id">Rutina</label>
                    </th>
                    <td>
                        <select name="routine_id" id="routine_id">
                            <option value="0">Registro manual</option>
                            <?php foreach ( $routines as $routine ) : ?>
                                <option value="<?php echo esc_attr( $routine->id ); ?>">
                                    <?php echo esc_html( gymtrack_routine_display_text( $routine->name ) ); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <p class="description">
                            Selecciona una rutina para cargar sus ejercicios automáticamente.
                        </p>
                    </td>
                </tr>

            </table>

            <div id="gymtrack-manual-exercise">

                <h3>Ejercicio manual</h3>

                <table class="form-table">

                    <tr>
                        <th>
                            <label for="exercise_id">Ejercicio</label>
                        </th>
                        <td>
                            <select name="exercise_id" id="exercise_id">
                                <option value="">Selecciona un ejercicio</option>
                                <?php foreach ( $exercises as $exercise ) : ?>
                                    <option value="<?php echo esc_attr( $exercise->id ); ?>">
                                        <?php echo esc_html( $exercise->name . ' — ' . $exercise->muscle_group ); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </td>
                    </tr>

                    <tr>
                        <th><label for="weight">Peso (kg)</label></th>
                        <td>
                            <input type="number" name="weight" id="weight" min="0" step="0.5" value="0">
                        </td>
                    </tr>

                    <tr>
                        <th><label for="sets">Series</label></th>
                        <td>
                            <input type="number" name="sets" id="sets" min="1" value="1">
                        </td>
                    </tr>

                    <tr>
                        <th><label for="repetitions">Repeticiones</label></th>
                        <td>
                            <input type="number" name="repetitions" id="repetitions" min="1" value="1">
                        </td>
                    </tr>

                </table>

            </div>

            <div id="gymtrack-routine-exercises" style="display:none;">

                <h3>Ejercicios de la rutina</h3>

                <div id="gymtrack-routine-description" class="notice notice-info inline" style="display:none;"></div>

                <table class="widefat striped" id="gymtrack-routine-table">
                    <thead>
                        <tr>
                            <th>Orden</th>
                            <th>Ejercicio</th>
                            <th>Series</th>
                            <th>Repeticiones</th>
                            <th>Peso real (kg)</th>
                            <th>Notas</th>
                        </tr>
                    </thead>
                    <tbody id="gymtrack-routine-body"></tbody>
                </table>

                <p class="description">
                    Las series y repeticiones se cargan desde la rutina como objetivo, pero puedes modificarlas para reflejar el entrenamiento realizado.
                </p>

            </div>

            <table class="form-table">
                <tr>
                    <th>
                        <label for="notes">Notas del entrenamiento</label>
                    </th>
                    <td>
                        <textarea name="notes" id="notes" rows="4" class="large-text"></textarea>
                    </td>
                </tr>
            </table>

            <p>
                <button
                    type="submit"
                    name="gymtrack_add_workout"
                    class="button button-primary"
                >
                    Registrar entrenamiento
                </button>
            </p>

        </form>

        <hr>

        <h2>Entrenamientos registrados</h2>

        <?php if ( empty( $workouts ) ) : ?>

            <p>No hay entrenamientos registrados todavía.</p>

        <?php else : ?>

            <table class="widefat striped">

                <thead>
                    <tr>
                        <th>ID</th>
                        <?php if ( gymtrack_is_admin() ) : ?>
                            <th>Usuario</th>
                        <?php endif; ?>
                        <th>Fecha</th>
                        <th>Ejercicio</th>
                        <th>Grupo muscular</th>
                        <th>Peso</th>
                        <th>Series</th>
                        <th>Repeticiones</th>
                        <th>Notas</th>
                    </tr>
                </thead>

                <tbody>
                    <?php foreach ( $workouts as $workout ) : ?>
                        <tr>
                            <td><?php echo esc_html( $workout->id ); ?></td>

                            <?php if ( gymtrack_is_admin() ) : ?>
                                <td><?php echo esc_html( $workout->user_login ); ?></td>
                            <?php endif; ?>

                            <td><?php echo esc_html( $workout->workout_date ); ?></td>
                            <td><strong><?php echo esc_html( $workout->exercise_name ); ?></strong></td>
                            <td><?php echo esc_html( $workout->muscle_group ); ?></td>
                            <td><?php echo esc_html( $workout->weight ); ?> kg</td>
                            <td><?php echo esc_html( $workout->sets ); ?></td>
                            <td><?php echo esc_html( $workout->repetitions ); ?></td>
                            <td><?php echo esc_html( $workout->notes ); ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>

            </table>

        <?php endif; ?>

    </div>

    <script>
    document.addEventListener('DOMContentLoaded', function () {

        const routineSelect = document.getElementById('routine_id');
        const manualBox = document.getElementById('gymtrack-manual-exercise');
        const routineBox = document.getElementById('gymtrack-routine-exercises');
        const routineBody = document.getElementById('gymtrack-routine-body');
        const routineDescription = document.getElementById('gymtrack-routine-description');
        const exerciseSelect = document.getElementById('exercise_id');
        const weightInput = document.getElementById('weight');
        const setsInput = document.getElementById('sets');
        const repetitionsInput = document.getElementById('repetitions');

        const routines = <?php echo wp_json_encode( $routine_data ); ?>;

        function clearRoutineRows() {
            routineBody.innerHTML = '';
            routineDescription.innerHTML = '';
            routineDescription.style.display = 'none';
        }

        function createRoutineRow(exercise, index) {
            const tr = document.createElement('tr');

            const orderTd = document.createElement('td');
            orderTd.textContent = exercise.exercise_order;

            const exerciseTd = document.createElement('td');
            const exerciseName = document.createElement('strong');
            exerciseName.textContent = exercise.exercise_name;
            exerciseTd.appendChild(exerciseName);
            exerciseTd.appendChild(document.createElement('br'));
            const muscle = document.createElement('span');
            muscle.textContent = exercise.muscle_group;
            exerciseTd.appendChild(muscle);

            const setsTd = document.createElement('td');
            const sets = document.createElement('input');
            sets.type = 'number';
            sets.name = 'routine_exercises[' + index + '][sets]';
            sets.min = '1';
            sets.value = exercise.target_sets;
            sets.required = true;
            sets.style.width = '80px';
            setsTd.appendChild(sets);

            const repsTd = document.createElement('td');
            const reps = document.createElement('input');
            reps.type = 'number';
            reps.name = 'routine_exercises[' + index + '][repetitions]';
            reps.min = '1';
            reps.value = exercise.target_repetitions;
            reps.required = true;
            reps.style.width = '80px';
            repsTd.appendChild(reps);

            const weightTd = document.createElement('td');
            const weight = document.createElement('input');
            weight.type = 'number';
            weight.name = 'routine_exercises[' + index + '][weight]';
            weight.min = '0';
            weight.step = '0.5';
            weight.value = '0';
            weight.required = true;
            weight.style.width = '100px';
            weightTd.appendChild(weight);

            const notesTd = document.createElement('td');
            const notes = document.createElement('textarea');
            notes.name = 'routine_exercises[' + index + '][notes]';
            notes.rows = 2;
            notes.style.width = '100%';
            notes.placeholder = exercise.notes ? exercise.notes : '';
            notesTd.appendChild(notes);

            const hidden = document.createElement('input');
            hidden.type = 'hidden';
            hidden.name = 'routine_exercises[' + index + '][exercise_id]';
            hidden.value = exercise.exercise_id;
            exerciseTd.appendChild(hidden);

            tr.appendChild(orderTd);
            tr.appendChild(exerciseTd);
            tr.appendChild(setsTd);
            tr.appendChild(repsTd);
            tr.appendChild(weightTd);
            tr.appendChild(notesTd);

            return tr;
        }

        function loadRoutine() {
            const routineId = routineSelect.value;
            clearRoutineRows();

            if (!routineId || routineId === '0' || !routines[routineId]) {
                manualBox.style.display = '';
                routineBox.style.display = 'none';
                exerciseSelect.required = true;
                weightInput.required = true;
                setsInput.required = true;
                repetitionsInput.required = true;
                return;
            }

            manualBox.style.display = 'none';
            routineBox.style.display = '';
            exerciseSelect.required = false;
            weightInput.required = false;
            setsInput.required = false;
            repetitionsInput.required = false;

            const routine = routines[routineId];

            if (routine.description) {
                routineDescription.textContent = routine.description;
                routineDescription.style.display = '';
            }

            if (!routine.exercises || routine.exercises.length === 0) {
                const emptyRow = document.createElement('tr');
                const emptyCell = document.createElement('td');
                emptyCell.colSpan = 6;
                emptyCell.textContent = 'Esta rutina no tiene ejercicios configurados.';
                emptyRow.appendChild(emptyCell);
                routineBody.appendChild(emptyRow);
                return;
            }

            routine.exercises.forEach(function (exercise, index) {
                routineBody.appendChild(createRoutineRow(exercise, index));
            });
        }

        routineSelect.addEventListener('change', loadRoutine);
        loadRoutine();
    });
    </script>

    <?php
}


/**
 * =========================================================
 * PÁGINA DE HISTORIAL
 * =========================================================
 */

function gymtrack_history_page() {

    global $wpdb;

    $table_workouts  = $wpdb->prefix . 'gymtrack_workouts';
    $table_sets      = $wpdb->prefix . 'gymtrack_sets';
    $table_exercises = $wpdb->prefix . 'gymtrack_exercises';


    if ( ! gymtrack_can_use() ) {
        return;
    }


    /*
     * Administrador ve todo.
     */

    if ( gymtrack_is_admin() ) {

        $history = $wpdb->get_results(
            "SELECT
                w.id AS workout_id,
                w.workout_date,
                w.notes AS workout_notes,
                u.user_login,
                e.name AS exercise_name,
                e.muscle_group,
                s.weight,
                s.sets,
                s.repetitions,
                s.notes AS set_notes
            FROM $table_workouts w
            INNER JOIN {$wpdb->users} u
                ON w.user_id = u.ID
            INNER JOIN $table_sets s
                ON w.id = s.workout_id
            INNER JOIN $table_exercises e
                ON s.exercise_id = e.id
            ORDER BY w.workout_date DESC, w.id DESC"
        );

    } else {

        /*
         * Usuario normal solo ve su historial.
         */

        $current_user_id = get_current_user_id();

        $history = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT
                    w.id AS workout_id,
                    w.workout_date,
                    w.notes AS workout_notes,
                    u.user_login,
                    e.name AS exercise_name,
                    e.muscle_group,
                    s.weight,
                    s.sets,
                    s.repetitions,
                    s.notes AS set_notes
                FROM $table_workouts w
                INNER JOIN {$wpdb->users} u
                    ON w.user_id = u.ID
                INNER JOIN $table_sets s
                    ON w.id = s.workout_id
                INNER JOIN $table_exercises e
                    ON s.exercise_id = e.id
                WHERE w.user_id = %d
                ORDER BY w.workout_date DESC, w.id DESC",
                $current_user_id
            )
        );
    }

    ?>

    <div class="wrap">

        <h1>GymTrack - Historial</h1>

        <p>
            <?php if ( gymtrack_is_admin() ) : ?>

                Historial de todos los entrenamientos
                registrados en GymTrack.

            <?php else : ?>

                Historial de tus entrenamientos
                registrados en GymTrack.

            <?php endif; ?>
        </p>


        <?php if ( empty( $history ) ) : ?>

            <div class="notice notice-info">

                <p>
                    No hay entrenamientos registrados todavía.
                </p>

            </div>

        <?php else : ?>

            <table class="widefat striped">

                <thead>

                    <tr>

                        <th>Fecha</th>

                        <?php if ( gymtrack_is_admin() ) : ?>
                            <th>Usuario</th>
                        <?php endif; ?>

                        <th>Ejercicio</th>
                        <th>Grupo muscular</th>
                        <th>Peso</th>
                        <th>Series</th>
                        <th>Repeticiones</th>
                        <th>Notas</th>

                    </tr>

                </thead>

                <tbody>

                    <?php foreach ( $history as $entry ) : ?>

                        <tr>

                            <td>
                                <?php
                                echo esc_html(
                                    $entry->workout_date
                                );
                                ?>
                            </td>

                            <?php if ( gymtrack_is_admin() ) : ?>

                                <td>
                                    <?php
                                    echo esc_html(
                                        $entry->user_login
                                    );
                                    ?>
                                </td>

                            <?php endif; ?>

                            <td>

                                <strong>
                                    <?php
                                    echo esc_html(
                                        $entry->exercise_name
                                    );
                                    ?>
                                </strong>

                            </td>

                            <td>
                                <?php
                                echo esc_html(
                                    $entry->muscle_group
                                );
                                ?>
                            </td>

                            <td>
                                <?php
                                echo esc_html(
                                    $entry->weight
                                );
                                ?>
                                kg
                            </td>

                            <td>
                                <?php
                                echo esc_html(
                                    $entry->sets
                                );
                                ?>
                            </td>

                            <td>
                                <?php
                                echo esc_html(
                                    $entry->repetitions
                                );
                                ?>
                            </td>

                            <td>
                                <?php
                                echo esc_html(
                                    $entry->set_notes
                                );
                                ?>
                            </td>

                        </tr>

                    <?php endforeach; ?>

                </tbody>

            </table>

        <?php endif; ?>

    </div>

    <?php
}


/**
 * =========================================================
 * PÁGINA DE PROGRESO
 * =========================================================
 */

function gymtrack_progress_page() {

    global $wpdb;

    $table_workouts  = $wpdb->prefix . 'gymtrack_workouts';
    $table_sets      = $wpdb->prefix . 'gymtrack_sets';
    $table_exercises = $wpdb->prefix . 'gymtrack_exercises';

    if ( ! gymtrack_can_use() ) {
        return;
    }

    /*
     * Obtener evolución.
     *
     * Se conservan todos los registros de entrenamiento con peso > 0.
     * Después agrupamos los datos por ejercicio para que cada línea
     * del gráfico represente solamente a un ejercicio.
     */
    if ( gymtrack_is_admin() ) {

        $progress = $wpdb->get_results(
            "SELECT
                e.id AS exercise_id,
                e.name AS exercise_name,
                s.weight,
                s.sets,
                s.repetitions,
                w.workout_date,
                w.id AS workout_id
            FROM $table_workouts w
            INNER JOIN $table_sets s
                ON w.id = s.workout_id
            INNER JOIN $table_exercises e
                ON s.exercise_id = e.id
            WHERE s.weight > 0
            ORDER BY e.name ASC, w.workout_date ASC, w.id ASC"
        );

    } else {

        $current_user_id = get_current_user_id();

        $progress = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT
                    e.id AS exercise_id,
                    e.name AS exercise_name,
                    s.weight,
                    s.sets,
                    s.repetitions,
                    w.workout_date,
                    w.id AS workout_id
                FROM $table_workouts w
                INNER JOIN $table_sets s
                    ON w.id = s.workout_id
                INNER JOIN $table_exercises e
                    ON s.exercise_id = e.id
                WHERE s.weight > 0
                AND w.user_id = %d
                ORDER BY e.name ASC, w.workout_date ASC, w.id ASC",
                $current_user_id
            )
        );
    }

    /*
     * Agrupar datos por ejercicio.
     */
    $progress_by_exercise = array();

    foreach ( $progress as $entry ) {

        $exercise_id = absint( $entry->exercise_id );

        if ( ! isset( $progress_by_exercise[ $exercise_id ] ) ) {
            $progress_by_exercise[ $exercise_id ] = array(
                'id'      => $exercise_id,
                'name'    => $entry->exercise_name,
                'records' => array(),
                'max'     => 0,
                'max_date'=> '',
            );
        }

        $progress_by_exercise[ $exercise_id ]['records'][] = array(
            'date'         => $entry->workout_date,
            'weight'       => (float) $entry->weight,
            'sets'         => absint( $entry->sets ),
            'repetitions'  => absint( $entry->repetitions ),
            'workout_id'   => absint( $entry->workout_id ),
        );

        if ( (float) $entry->weight > $progress_by_exercise[ $exercise_id ]['max'] ) {
            $progress_by_exercise[ $exercise_id ]['max']      = (float) $entry->weight;
            $progress_by_exercise[ $exercise_id ]['max_date'] = $entry->workout_date;
        }
    }

    /*
     * Ordenar ejercicios alfabéticamente para el selector y el resumen.
     */
    uasort(
        $progress_by_exercise,
        function ( $a, $b ) {
            return strcasecmp( $a['name'], $b['name'] );
        }
    );

    $chart_data = array();

    foreach ( $progress_by_exercise as $exercise ) {
        $chart_data[] = array(
            'id'      => $exercise['id'],
            'name'    => $exercise['name'],
            'labels'  => array_column( $exercise['records'], 'date' ),
            'weights' => array_column( $exercise['records'], 'weight' ),
        );
    }

    ?>

    <div class="wrap">

        <h1>GymTrack - Progreso</h1>

        <p>
            <?php if ( gymtrack_is_admin() ) : ?>
                Evolución del peso de todos los entrenamientos registrados.
            <?php else : ?>
                Evolución del peso de tus entrenamientos.
            <?php endif; ?>
        </p>

        <?php if ( empty( $progress ) ) : ?>

            <div class="notice notice-info">
                <p>
                    Todavía no hay datos suficientes para mostrar el progreso.
                </p>
            </div>

        <?php else : ?>

            <h2>Mejores marcas</h2>

            <table class="widefat striped">

                <thead>
                    <tr>
                        <th>Ejercicio</th>
                        <th>Mejor peso</th>
                        <th>Fecha de la mejor marca</th>
                    </tr>
                </thead>

                <tbody>

                    <?php foreach ( $progress_by_exercise as $exercise ) : ?>

                        <tr>
                            <td>
                                <strong>
                                    <?php echo esc_html( $exercise['name'] ); ?>
                                </strong>
                            </td>

                            <td>
                                <?php echo esc_html( number_format_i18n( $exercise['max'], 2 ) ); ?> kg
                            </td>

                            <td>
                                <?php echo esc_html( $exercise['max_date'] ); ?>
                            </td>
                        </tr>

                    <?php endforeach; ?>

                </tbody>

            </table>

            <br>

            <h2>Historial de evolución</h2>

            <table class="widefat striped">

                <thead>
                    <tr>
                        <th>Ejercicio</th>
                        <th>Fecha</th>
                        <th>Peso</th>
                        <th>Series</th>
                        <th>Repeticiones</th>
                    </tr>
                </thead>

                <tbody>

                    <?php foreach ( $progress as $entry ) : ?>

                        <tr>
                            <td>
                                <strong>
                                    <?php echo esc_html( $entry->exercise_name ); ?>
                                </strong>
                            </td>

                            <td><?php echo esc_html( $entry->workout_date ); ?></td>

                            <td><?php echo esc_html( $entry->weight ); ?> kg</td>

                            <td><?php echo esc_html( $entry->sets ); ?></td>

                            <td><?php echo esc_html( $entry->repetitions ); ?></td>
                        </tr>

                    <?php endforeach; ?>

                </tbody>

            </table>

            <br>

            <h2>Evolución por ejercicio</h2>

            <p>
                <label for="gymtrack-progress-exercise">
                    <strong>Ejercicio:</strong>
                </label>

                <select id="gymtrack-progress-exercise">
                    <?php foreach ( $progress_by_exercise as $exercise ) : ?>
                        <option value="<?php echo esc_attr( $exercise['id'] ); ?>">
                            <?php echo esc_html( $exercise['name'] ); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </p>

            <canvas
                id="gymtrackProgressChart"
                height="100"
            ></canvas>

            <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

            <script>
                document.addEventListener(
                    'DOMContentLoaded',
                    function () {

                        const progressData = <?php echo wp_json_encode( $chart_data ); ?>;
                        const selector = document.getElementById(
                            'gymtrack-progress-exercise'
                        );
                        const canvas = document.getElementById(
                            'gymtrackProgressChart'
                        );

                        if ( ! selector || ! canvas || ! progressData.length ) {
                            return;
                        }

                        const ctx = canvas.getContext('2d');
                        let chart = null;

                        function getExerciseData(id) {
                            return progressData.find(function (item) {
                                return String(item.id) === String(id);
                            });
                        }

                        function renderChart() {

                            const exercise = getExerciseData(selector.value);

                            if ( ! exercise ) {
                                return;
                            }

                            if ( chart ) {
                                chart.destroy();
                            }

                            chart = new Chart(
                                ctx,
                                {
                                    type: 'line',
                                    data: {
                                        labels: exercise.labels,
                                        datasets: [{
                                            label: exercise.name + ' - Peso (kg)',
                                            data: exercise.weights,
                                            tension: 0.3,
                                            fill: false
                                        }]
                                    },
                                    options: {
                                        responsive: true,
                                        scales: {
                                            y: {
                                                beginAtZero: true,
                                                title: {
                                                    display: true,
                                                    text: 'Peso (kg)'
                                                }
                                            },
                                            x: {
                                                title: {
                                                    display: true,
                                                    text: 'Fecha'
                                                }
                                            }
                                        }
                                    }
                                }
                            );
                        }

                        selector.addEventListener(
                            'change',
                            renderChart
                        );

                        renderChart();
                    }
                );
            </script>

        <?php endif; ?>

    </div>

    <?php
}
