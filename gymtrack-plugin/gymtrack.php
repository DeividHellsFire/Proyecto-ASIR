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
 * Oculta la antigua página de ejemplo de WordPress del menú público.
 * Se mantienen únicamente las páginas propias de GymTrack.
 */

function gymtrack_remove_default_example_page_from_menu( $items, $args ) {

    foreach ( $items as $key => $item ) {
        $url = isset( $item->url ) ? (string) $item->url : '';
        $title = isset( $item->title ) ? trim( wp_strip_all_tags( $item->title ) ) : '';

        if (
            false !== strpos( $url, 'page_id=2' ) ||
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
 * Twenty Twenty-Five utiliza navegación basada en bloques en algunas
 * configuraciones. Este CSS actúa como segunda barrera para que la página
 * de ejemplo no vuelva a aparecer en el menú público.
 */
function gymtrack_hide_default_example_page_from_block_navigation() {
    ?>
    <style id="gymtrack-public-navigation">
        a[href*="page_id=2"] {
            display: none !important;
        }
    </style>
    <?php
}

add_action(
    'wp_head',
    'gymtrack_hide_default_example_page_from_block_navigation',
    100
);

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

    <style>
        .gymtrack-dashboard {
            max-width: 1100px;
            margin: 30px auto;
            padding: 0 20px;
            font-family: inherit;
        }
        .gymtrack-dashboard h1 {
            margin-bottom: 8px;
        }
        .gymtrack-dashboard .gymtrack-subtitle {
            margin-bottom: 25px;
            color: #666;
        }
        .gymtrack-dashboard-grid {
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: 20px;
            margin-bottom: 25px;
        }
        .gymtrack-card {
            background: #fff;
            border: 1px solid #ddd;
            border-radius: 8px;
            padding: 20px;
            box-sizing: border-box;
        }
        .gymtrack-stat {
            font-size: 32px;
            font-weight: 700;
            margin: 8px 0 0;
        }
        .gymtrack-stat-label {
            color: #666;
            margin: 0;
        }
        .gymtrack-dashboard table {
            width: 100%;
            border-collapse: collapse;
        }
        .gymtrack-dashboard th,
        .gymtrack-dashboard td {
            padding: 10px;
            border-bottom: 1px solid #eee;
            text-align: left;
        }
        .gymtrack-dashboard .gymtrack-actions {
            display: flex;
            flex-wrap: wrap;
            gap: 10px;
            margin-top: 15px;
        }
        .gymtrack-dashboard .gymtrack-button {
            display: inline-block;
            padding: 10px 16px;
            border-radius: 5px;
            background: #2271b1;
            color: #fff;
            text-decoration: none;
        }
        .gymtrack-dashboard .gymtrack-button:hover {
            color: #fff;
            opacity: .9;
        }
        .gymtrack-dashboard .gymtrack-empty {
            color: #666;
        }
        @media (max-width: 700px) {
            .gymtrack-dashboard-grid {
                grid-template-columns: 1fr;
            }
        }
    </style>

    <div class="gymtrack-dashboard">
        <h1>GymTrack</h1>
        <p class="gymtrack-subtitle">
            Bienvenido, <?php echo esc_html( wp_get_current_user()->display_name ?: wp_get_current_user()->user_login ); ?>.
        </p>

        <div class="gymtrack-dashboard-grid">
            <div class="gymtrack-card">
                <p class="gymtrack-stat-label">Entrenamientos registrados</p>
                <p class="gymtrack-stat"><?php echo esc_html( $total_workouts ); ?></p>
            </div>
            <div class="gymtrack-card">
                <p class="gymtrack-stat-label">Ejercicios realizados</p>
                <p class="gymtrack-stat"><?php echo esc_html( $total_exercises ); ?></p>
            </div>
        </div>

        <div class="gymtrack-card" style="margin-bottom:20px;">
            <h2>Últimos entrenamientos</h2>

            <?php if ( empty( $recent_workouts ) ) : ?>
                <p class="gymtrack-empty">Todavía no tienes entrenamientos registrados.</p>
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

        <div class="gymtrack-card" style="margin-bottom:20px;">
            <h2>Mejores marcas</h2>

            <?php if ( empty( $best_marks ) ) : ?>
                <p class="gymtrack-empty">Todavía no tienes marcas registradas.</p>
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

        <div class="gymtrack-card">
            <h2>Acciones rápidas</h2>
            <div class="gymtrack-actions">
                <a class="gymtrack-button" href="<?php echo esc_url( gymtrack_frontend_page_url( 'gymtrack-registrar' ) ); ?>">Registrar entrenamiento</a>
                <a class="gymtrack-button" href="<?php echo esc_url( gymtrack_frontend_page_url( 'gymtrack-historial' ) ); ?>">Ver historial</a>
                <a class="gymtrack-button" href="<?php echo esc_url( gymtrack_frontend_page_url( 'gymtrack-progreso' ) ); ?>">Ver progreso</a>
                <a class="gymtrack-button" href="<?php echo esc_url( gymtrack_frontend_page_url( 'gymtrack-biblioteca' ) ); ?>">Biblioteca de ejercicios</a>
            </div>
        </div>
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
        .gymtrack-frontend{max-width:1100px;margin:30px auto;padding:0 20px;font-family:inherit}.gymtrack-frontend h1{margin-bottom:8px}.gymtrack-frontend .gymtrack-card{background:#fff;border:1px solid #ddd;border-radius:8px;padding:20px;margin-bottom:20px;box-sizing:border-box}.gymtrack-frontend table{width:100%;border-collapse:collapse}.gymtrack-frontend th,.gymtrack-frontend td{padding:10px;border-bottom:1px solid #eee;text-align:left}.gymtrack-frontend input,.gymtrack-frontend select,.gymtrack-frontend textarea{max-width:100%;box-sizing:border-box;padding:8px}.gymtrack-frontend label{font-weight:600;display:block;margin-bottom:6px}.gymtrack-frontend .field{margin-bottom:16px}.gymtrack-frontend .grid{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:16px}.gymtrack-frontend .button{display:inline-block;padding:10px 16px;border:0;border-radius:5px;background:#2271b1;color:#fff;text-decoration:none;cursor:pointer}.gymtrack-frontend .button.secondary{background:#666}.gymtrack-frontend .message{padding:12px 16px;border-radius:5px;margin-bottom:20px;background:#f0f6fc;border-left:4px solid #2271b1}.gymtrack-frontend .message.success{background:#edf7ed;border-left-color:#46b450}.gymtrack-frontend .message.error{background:#fbeaea;border-left-color:#dc3232}.gymtrack-frontend .actions{display:flex;gap:10px;flex-wrap:wrap;margin-top:18px}.gymtrack-frontend .muted{color:#666}.gymtrack-frontend .card-grid{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:20px}.gymtrack-frontend .exercise-card{border:1px solid #ddd;border-radius:8px;padding:18px}.gymtrack-frontend img{max-width:180px;height:auto;border-radius:6px}@media(max-width:700px){.gymtrack-frontend .grid,.gymtrack-frontend .card-grid{grid-template-columns:1fr}.gymtrack-frontend{padding:0 12px}.gymtrack-frontend table{font-size:14px}}
    </style>';
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

        if ( '' === $date ) {
            $message = 'La fecha es obligatoria.';
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
                    $exists = $wpdb->get_var($wpdb->prepare("SELECT id FROM $table_exercises WHERE id=%d LIMIT 1", $exercise_id));
                    if ( $exists && $weight > 0 && $sets > 0 && $reps > 0 ) {
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
    <div class="gymtrack-frontend">
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
    ob_start();echo gymtrack_frontend_styles();?><div class="gymtrack-frontend"><h1>Mi historial</h1><p class="muted">Tus entrenamientos registrados en GymTrack.</p><div class="gymtrack-card"><?php if(empty($rows)):?><p>No hay entrenamientos válidos registrados todavía.</p><?php else:?><div style="overflow-x:auto"><table><thead><tr><th>Fecha</th><th>Ejercicio</th><th>Grupo muscular</th><th>Peso</th><th>Series</th><th>Repeticiones</th><th>Notas</th></tr></thead><tbody><?php foreach($rows as $r):?><tr><td><?php echo esc_html($r->workout_date);?></td><td><strong><?php echo esc_html($r->exercise_name);?></strong></td><td><?php echo esc_html($r->muscle_group);?></td><td><?php echo esc_html(number_format_i18n((float)$r->weight,2));?> kg</td><td><?php echo esc_html($r->sets);?></td><td><?php echo esc_html($r->repetitions);?></td><td><?php echo esc_html($r->notes);?></td></tr><?php endforeach;?></tbody></table></div><?php endif;?></div><div class="actions"><a class="button" href="<?php echo esc_url(gymtrack_frontend_page_url('gymtrack-registrar'));?>">Registrar entrenamiento</a><a class="button secondary" href="<?php echo esc_url(home_url('/gymtrack/'));?>">Volver a GymTrack</a></div></div><?php return ob_get_clean();
}
add_shortcode('gymtrack_history','gymtrack_frontend_history_shortcode');

function gymtrack_frontend_progress_shortcode() {
    $access=gymtrack_frontend_access_check(); if(''!==$access){return $access;} global $wpdb;
    $tw=$wpdb->prefix.'gymtrack_workouts';$ts=$wpdb->prefix.'gymtrack_sets';$te=$wpdb->prefix.'gymtrack_exercises';
    $rows=$wpdb->get_results($wpdb->prepare("SELECT e.id exercise_id,e.name exercise_name,w.workout_date,s.weight,s.sets,s.repetitions FROM $tw w INNER JOIN $ts s ON w.id=s.workout_id INNER JOIN $te e ON s.exercise_id=e.id WHERE w.user_id=%d AND s.weight>0 ORDER BY e.name ASC,w.workout_date ASC,w.id ASC",get_current_user_id()));
    $best=array();$chart=array();foreach($rows as $r){$chart[$r->exercise_id]['name']=$r->exercise_name;$chart[$r->exercise_id]['labels'][]=$r->workout_date;$chart[$r->exercise_id]['data'][]=(float)$r->weight;if(!isset($best[$r->exercise_id])||(float)$r->weight>(float)$best[$r->exercise_id]->weight){$best[$r->exercise_id]=$r;}}
    ob_start();echo gymtrack_frontend_styles();?><div class="gymtrack-frontend"><h1>Mi progreso</h1><p class="muted">Evolución de tus pesos y mejores marcas.</p><div class="gymtrack-card"><h2>Mejores marcas</h2><?php if(empty($best)):?><p>No hay datos de progreso todavía.</p><?php else:?><table><thead><tr><th>Ejercicio</th><th>Mejor peso</th><th>Fecha</th></tr></thead><tbody><?php foreach($best as $r):?><tr><td><?php echo esc_html($r->exercise_name);?></td><td><?php echo esc_html(number_format_i18n((float)$r->weight,2));?> kg</td><td><?php echo esc_html($r->workout_date);?></td></tr><?php endforeach;?></tbody></table><?php endif;?></div><div class="gymtrack-card"><h2>Evolución</h2><?php if(empty($chart)):?><p>No hay datos para mostrar.</p><?php else:?><div class="field"><label for="gt-progress-exercise">Ejercicio</label><select id="gt-progress-exercise"><?php foreach($chart as $id=>$data):?><option value="<?php echo esc_attr($id);?>"><?php echo esc_html($data['name']);?></option><?php endforeach;?></select></div><canvas id="gt-progress-chart" height="120"></canvas><?php endif;?></div><div class="actions"><a class="button" href="<?php echo esc_url(gymtrack_frontend_page_url('gymtrack-registrar'));?>">Registrar entrenamiento</a><a class="button secondary" href="<?php echo esc_url(home_url('/gymtrack/'));?>">Volver a GymTrack</a></div></div>
    <?php if(!empty($chart)):?><script src="https://cdn.jsdelivr.net/npm/chart.js"></script><script>document.addEventListener('DOMContentLoaded',function(){const data=<?php echo wp_json_encode($chart);?>;const sel=document.getElementById('gt-progress-exercise'),ctx=document.getElementById('gt-progress-chart').getContext('2d');let chart;function draw(){const d=data[sel.value];if(chart){chart.destroy();}chart=new Chart(ctx,{type:'line',data:{labels:d.labels,datasets:[{label:d.name+' (kg)',data:d.data,tension:.2}]},options:{responsive:true,scales:{y:{beginAtZero:false}}}});}sel.addEventListener('change',draw);draw();});</script><?php endif;?><?php return ob_get_clean();
}
add_shortcode('gymtrack_progress','gymtrack_frontend_progress_shortcode');

function gymtrack_frontend_library_shortcode() {
    $access=gymtrack_frontend_access_check(); if(''!==$access){return $access;}
    $search=sanitize_text_field(isset($_GET['gymtrack_search'])?wp_unslash($_GET['gymtrack_search']):'');$results=array();$error='';
    if(''!==$search){$cache_key='gymtrack_wger_'.md5(strtolower($search));$cached=get_transient($cache_key);if(false!==$cached){$results=$cached;}else{$response=wp_remote_get(add_query_arg(array('name__search'=>$search,'limit'=>12),'https://wger.de/api/v2/exerciseinfo/'),array('timeout'=>10,'headers'=>array('Accept'=>'application/json')));if(is_wp_error($response)){$error=$response->get_error_message();}else{$code=wp_remote_retrieve_response_code($response);$body=json_decode(wp_remote_retrieve_body($response),true);if(200!==$code||!is_array($body)){$error='No se ha podido consultar la biblioteca externa.';}else{$results=isset($body['results'])&&is_array($body['results'])?$body['results']:array();set_transient($cache_key,$results,10*MINUTE_IN_SECONDS);}}}}
    ob_start();echo gymtrack_frontend_styles();?><div class="gymtrack-frontend"><h1>Biblioteca de ejercicios</h1><p class="muted">Consulta ejercicios de la biblioteca pública de wger.</p><div class="gymtrack-card"><form method="get" action="<?php echo esc_url( gymtrack_frontend_page_url( 'gymtrack-biblioteca' ) ); ?>"><div class="field"><label for="gt-search">Buscar ejercicio</label><input id="gt-search" type="search" name="gymtrack_search" value="<?php echo esc_attr($search);?>" placeholder="Ej.: press banca"><button class="button" type="submit">Buscar</button></div></form></div><?php if($error):?><div class="message error"><p><?php echo esc_html($error);?></p></div><?php endif;?><?php if(''!==$search&&!$error):?><div class="card-grid"><?php foreach($results as $item){$name='';$description='';$category='';$translations=isset($item['translations'])&&is_array($item['translations'])?$item['translations']:array();foreach($translations as $tr){if(isset($tr['language'])&&is_array($tr['language'])&&isset($tr['language']['short_name'])&&'es'===$tr['language']['short_name']){$name=$tr['name']??'';$description=wp_strip_all_tags($tr['description']??'');break;}}if(''===$name&&isset($item['name']))$name=$item['name'];if(''===$description&&isset($item['description']))$description=wp_strip_all_tags($item['description']);if(isset($item['category'])&&is_array($item['category']))$category=$item['category']['name']??'';?><div class="exercise-card"><h2><?php echo esc_html($name?:'Ejercicio');?></h2><?php if($category):?><p><strong>Categoría:</strong> <?php echo esc_html($category);?></p><?php endif;?><?php if($description):?><p><?php echo esc_html(wp_trim_words($description,45));?></p><?php endif;?><?php if(isset($item['id'])):?><p class="muted">ID externo: <?php echo esc_html($item['id']);?></p><?php endif;?></div><?php }?></div><?php elseif(''===$search):?><div class="gymtrack-card"><p>Introduce un término para buscar ejercicios.</p></div><?php endif;?><div class="actions"><a class="button secondary" href="<?php echo esc_url(home_url('/gymtrack/'));?>">Volver a GymTrack</a></div></div><?php return ob_get_clean();
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
add_action('init','gymtrack_ensure_frontend_pages',20);


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

add_action( 'init', 'gymtrack_ensure_frontend_dashboard_page', 20 );


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
    $hello_world = get_page_by_title( '¡Hola, mundo!' );
    if ( $hello_world instanceof WP_Post && 'post' === $hello_world->post_type ) {
        wp_trash_post( $hello_world->ID );
    }

    $hello_world_en = get_page_by_title( 'Hello world!' );
    if ( $hello_world_en instanceof WP_Post && 'post' === $hello_world_en->post_type ) {
        wp_trash_post( $hello_world_en->ID );
    }

    $sample_page = get_page_by_path( 'pagina-de-ejemplo' );
    if ( ! $sample_page instanceof WP_Post ) {
        $sample_page = get_page_by_path( 'sample-page' );
    }

    if ( $sample_page instanceof WP_Post ) {
        wp_trash_post( $sample_page->ID );
    }
}
add_action( 'init', 'gymtrack_configure_site_as_app', 30 );

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

    if ( ! gymtrack_can_manage() ) {
        return;
    }


    /*
     * Eliminar ejercicio
     */

    if (
        isset( $_GET['action'] ) &&
        $_GET['action'] === 'delete' &&
        isset( $_GET['exercise_id'] )
    ) {

        $exercise_id = absint( $_GET['exercise_id'] );

        check_admin_referer(
            'gymtrack_delete_exercise_' . $exercise_id
        );

        $wpdb->delete(
            $table_exercises,
            array(
                'id' => $exercise_id,
            ),
            array(
                '%d',
            )
        );

        echo '<div class="notice notice-success is-dismissible">';
        echo '<p>Ejercicio eliminado correctamente.</p>';
        echo '</div>';
    }


    /*
     * Añadir ejercicio
     */

    if (
        isset( $_POST['gymtrack_add_exercise'] ) &&
        check_admin_referer( 'gymtrack_add_exercise' )
    ) {

        $name = sanitize_text_field( $_POST['name'] );
        $muscle_group = sanitize_text_field( $_POST['muscle_group'] );
        $description = sanitize_textarea_field( $_POST['description'] );

        if ( ! empty( $name ) && ! empty( $muscle_group ) ) {

            $wpdb->insert(
                $table_exercises,
                array(
                    'name'         => $name,
                    'muscle_group' => $muscle_group,
                    'description'  => $description,
                ),
                array(
                    '%s',
                    '%s',
                    '%s',
                )
            );

            echo '<div class="notice notice-success is-dismissible">';
            echo '<p>Ejercicio añadido correctamente.</p>';
            echo '</div>';

        } else {

            echo '<div class="notice notice-error is-dismissible">';
            echo '<p>El nombre y el grupo muscular son obligatorios.</p>';
            echo '</div>';
        }
    }


    /*
     * Obtener ejercicios
     */

    $exercises = $wpdb->get_results(
        "SELECT *
        FROM $table_exercises
        ORDER BY name ASC"
    );
    ?>

    <div class="wrap">

        <h1>GymTrack - Ejercicios</h1>

        <h2>Añadir ejercicio</h2>

        <form method="post">

            <?php wp_nonce_field( 'gymtrack_add_exercise' ); ?>

            <table class="form-table">

                <tr>
                    <th>
                        <label for="name">Nombre</label>
                    </th>

                    <td>
                        <input
                            type="text"
                            name="name"
                            id="name"
                            class="regular-text"
                            required
                        >
                    </td>
                </tr>

                <tr>
                    <th>
                        <label for="muscle_group">
                            Grupo muscular
                        </label>
                    </th>

                    <td>
                        <input
                            type="text"
                            name="muscle_group"
                            id="muscle_group"
                            class="regular-text"
                            required
                        >
                    </td>
                </tr>

                <tr>
                    <th>
                        <label for="description">
                            Descripción
                        </label>
                    </th>

                    <td>
                        <textarea
                            name="description"
                            id="description"
                            rows="4"
                            class="large-text"
                        ></textarea>
                    </td>
                </tr>

            </table>

            <p>

                <button
                    type="submit"
                    name="gymtrack_add_exercise"
                    class="button button-primary"
                >
                    Añadir ejercicio
                </button>

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

                            <td>
                                <?php echo esc_html( $exercise->id ); ?>
                            </td>

                            <td>
                                <strong>
                                    <?php echo esc_html( $exercise->name ); ?>
                                </strong>
                            </td>

                            <td>
                                <?php echo esc_html( $exercise->muscle_group ); ?>
                            </td>

                            <td>
                                <?php echo esc_html( $exercise->description ); ?>
                            </td>

                            <td>

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
                                    onclick="return confirm('¿Seguro que quieres eliminar este ejercicio?');"
                                >
                                    Eliminar
                                </a>

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
 * PÁGINA DE RUTINAS
 * =========================================================
 */

function gymtrack_routines_page() {

    global $wpdb;

    $table_routines = $wpdb->prefix . 'gymtrack_routines';

    if ( ! gymtrack_can_manage() ) {
        return;
    }


    /*
     * Añadir rutina
     */

    if ( isset( $_POST['gymtrack_add_routine'] ) ) {

        check_admin_referer( 'gymtrack_add_routine' );

        $name = sanitize_text_field(
            $_POST['routine_name']
        );

        $description = sanitize_textarea_field(
            $_POST['routine_description']
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
        }
    }


    /*
     * Añadir ejercicio a rutina
     */

    if ( isset( $_POST['gymtrack_add_routine_exercise'] ) ) {

        $routine_id = absint( $_POST['routine_id'] );
        $exercise_id = absint( $_POST['exercise_id'] );
        $exercise_order = absint( $_POST['exercise_order'] );
        $target_sets = absint( $_POST['target_sets'] );
        $target_repetitions = absint( $_POST['target_repetitions'] );
        $rest_seconds = absint( $_POST['rest_seconds'] );
        $notes = sanitize_textarea_field(
            $_POST['routine_exercise_notes']
        );

        check_admin_referer(
            'gymtrack_add_routine_exercise_' . $routine_id
        );

        if ( $routine_id > 0 && $exercise_id > 0 ) {

            if ( $exercise_order < 1 ) {
                $exercise_order = 1;
            }

            if ( $target_sets < 1 ) {
                $target_sets = 1;
            }

            if ( $target_repetitions < 1 ) {
                $target_repetitions = 1;
            }

            if ( $rest_seconds < 0 ) {
                $rest_seconds = 0;
            }

            $table_routine_exercises =
                $wpdb->prefix . 'gymtrack_routine_exercises';


            /*
             * Evitar duplicados
             */

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

            $table_routine_exercises =
                $wpdb->prefix . 'gymtrack_routine_exercises';

            $wpdb->delete(
                $table_routine_exercises,
                array(
                    'id' => $routine_exercise_id,
                ),
                array(
                    '%d',
                )
            );
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

            $table_routine_exercises =
                $wpdb->prefix . 'gymtrack_routine_exercises';


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

                $table_routine_exercises =
                    $wpdb->prefix . 'gymtrack_routine_exercises';

                $table_exercises =
                    $wpdb->prefix . 'gymtrack_exercises';


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
                            <?php echo esc_html( $routine->name ); ?>
                        </h3>

                        <p>
                            <?php echo esc_html( $routine->description ); ?>
                        </p>

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
                                                $routine_exercise->name
                                            );
                                            ?>
                                        </strong>

                                        —
                                        <?php
                                        echo esc_html(
                                            $routine_exercise->muscle_group
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
                                                $routine_exercise->notes
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

                                        <br><br>

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

                                    <label for="exercise_id">

                                        <strong>
                                            Ejercicio
                                        </strong>

                                    </label>

                                </p>

                                <select
                                    name="exercise_id"
                                    id="exercise_id"
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

                                    <label for="exercise_order">

                                        <strong>
                                            Orden
                                        </strong>

                                    </label>

                                    <br>

                                    <input
                                        type="number"
                                        name="exercise_order"
                                        id="exercise_order"
                                        value="1"
                                        min="1"
                                        style="width:80px;"
                                    >

                                </p>

                                <p>

                                    <label for="target_sets">

                                        <strong>
                                            Series objetivo
                                        </strong>

                                    </label>

                                    <br>

                                    <input
                                        type="number"
                                        name="target_sets"
                                        id="target_sets"
                                        value="3"
                                        min="1"
                                        required
                                        style="width:80px;"
                                    >

                                </p>

                                <p>

                                    <label for="target_repetitions">

                                        <strong>
                                            Repeticiones objetivo
                                        </strong>

                                    </label>

                                    <br>

                                    <input
                                        type="number"
                                        name="target_repetitions"
                                        id="target_repetitions"
                                        value="10"
                                        min="1"
                                        required
                                        style="width:80px;"
                                    >

                                </p>

                                <p>

                                    <label for="rest_seconds">

                                        <strong>
                                            Descanso (segundos)
                                        </strong>

                                    </label>

                                    <br>

                                    <input
                                        type="number"
                                        name="rest_seconds"
                                        id="rest_seconds"
                                        value="90"
                                        min="0"
                                        required
                                        style="width:100px;"
                                    >

                                </p>

                                <p>

                                    <label for="routine_exercise_notes">

                                        <strong>
                                            Notas
                                        </strong>

                                    </label>

                                    <br>

                                    <textarea
                                        name="routine_exercise_notes"
                                        id="routine_exercise_notes"
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
                                    <?php echo esc_html( $routine->name ); ?>
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
