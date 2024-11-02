<?php
/**
 * Plugin Name: WordStats Pro
 * Description: Muestra el número de palabras, tiempo estimado de lectura e índice de legibilidad en los posts.
 * Version: 2.1
 * Author: Pedro Luis Cuevas Villarrubia
 * Author URI: https://asturwebs.es/
 * License: GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 */

// Evitar acceso directo al archivo
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Calcula el índice de legibilidad Fernández-Huerta
 * 
 * @param string $texto El texto a analizar
 * @return array Array con el índice y la descripción
 */
function wordstats_pro_calcular_flesch($texto) {
    // Limpiar el texto
    $texto = wp_strip_all_tags($texto);
    
    // Contar sílabas, palabras y oraciones
    $palabras = str_word_count($texto, 0, 'áéíóúüñÁÉÍÓÚÜÑ');
    $oraciones = max(1, preg_match_all('/[.!?]["\'\)\]]*\s/u', $texto . ' ', $matches));
    $silabas = wordstats_pro_contar_silabas($texto);
    
    // Evitar división por cero
    if ($palabras == 0 || $oraciones == 0) {
        return array(
            'indice' => 0,
            'nivel' => 'No hay suficiente texto para analizar'
        );
    }

    // Fórmula Fernández-Huerta
    $indice = 206.84 - (60 * ($silabas / $palabras)) - (1.02 * ($palabras / $oraciones));

    // Determinar nivel de legibilidad
    $nivel = wordstats_pro_obtener_nivel_legibilidad($indice);

    return array(
        'indice' => round($indice, 2),
        'nivel' => $nivel
    );
}

/**
 * Cuenta las sílabas en un texto en español
 */
function wordstats_pro_contar_silabas($texto) {
    $texto = strtolower($texto);
    $texto = str_replace(['á', 'é', 'í', 'ó', 'ú'], ['a', 'e', 'i', 'o', 'u'], $texto);
    
    // Dividir el texto en palabras
    $palabras = preg_split('/[^a-záéíóúüñ]+/u', $texto, -1, PREG_SPLIT_NO_EMPTY);
    $total_silabas = 0;

    foreach ($palabras as $palabra) {
        if (empty($palabra)) continue;
        
        // Contar vocales
        $silabas = preg_match_all('/[aeiouáéíóú]/i', $palabra, $matches);
        
        // Restar diptongos
        $diptongos = preg_match_all('/(ai|au|ei|eu|io|ie|ia|oi|ou|ua|ue|ui|uo)/i', $palabra, $matches);
        $silabas -= $diptongos;
        
        // Restar triptongos
        $triptongos = preg_match_all('/(iai|iei|uai|uei|uau)/i', $palabra, $matches);
        $silabas -= $triptongos * 2;
        
        // Ajustar para hiatos
        $hiatos = preg_match_all('/(aí|aú|eí|eú|ía|íe|ío|oí|oú|úa|úe|úo)/i', $palabra, $matches);
        $silabas += $hiatos;
        
        $total_silabas += max(1, $silabas);
    }
    
    return $total_silabas;
}

/**
 * Determina el nivel de legibilidad según el índice Fernández-Huerta
 */
function wordstats_pro_obtener_nivel_legibilidad($indice) {
    if ($indice >= 90) return 'Muy fácil';
    if ($indice >= 80) return 'Fácil';
    if ($indice >= 70) return 'Algo fácil';
    if ($indice >= 60) return 'Normal';
    if ($indice >= 50) return 'Algo difícil';
    if ($indice >= 30) return 'Difícil';
    return 'Muy difícil';
}

/**
 * Obtiene la descripción detallada del nivel de legibilidad
 */
function wordstats_pro_obtener_descripcion_nivel($indice) {
    if ($indice >= 90) {
        return array(
            'nivel' => 'Muy fácil',
            'rango' => '90 - 100',
            'academico' => 'Primaria baja (1° a 3° grado)',
            'descripcion' => 'Adecuado para lectores jóvenes, con oraciones cortas y vocabulario sencillo.'
        );
    } elseif ($indice >= 80) {
        return array(
            'nivel' => 'Fácil',
            'rango' => '80 - 89',
            'academico' => 'Primaria media (4° a 6° grado)',
            'descripcion' => 'Textos simples y directos, adecuados para niños o adultos con lectura básica.'
        );
    } elseif ($indice >= 70) {
        return array(
            'nivel' => 'Algo fácil',
            'rango' => '70 - 79',
            'academico' => 'Secundaria baja (7° a 8° grado)',
            'descripcion' => 'Vocabulario comprensible y oraciones claras; ideal para público general.'
        );
    } elseif ($indice >= 60) {
        return array(
            'nivel' => 'Normal',
            'rango' => '60 - 69',
            'academico' => 'Secundaria alta (9° a 10° grado)',
            'descripcion' => 'Comprensible para adolescentes y adultos jóvenes; usado en artículos generales.'
        );
    } elseif ($indice >= 50) {
        return array(
            'nivel' => 'Algo difícil',
            'rango' => '50 - 59',
            'academico' => 'Bachillerato',
            'descripcion' => 'Requiere un lector más experimentado; textos técnicos y académicos simples.'
        );
    } elseif ($indice >= 30) {
        return array(
            'nivel' => 'Difícil',
            'rango' => '30 - 49',
            'academico' => 'Nivel universitario',
            'descripcion' => 'Adecuado para lectores con habilidades avanzadas en comprensión; común en textos académicos y profesionales.'
        );
    } else {
        return array(
            'nivel' => 'Muy difícil',
            'rango' => '0 - 29',
            'academico' => 'Postgrado y profesionales',
            'descripcion' => 'Textos muy complejos, con terminología técnica o lenguaje especializado; común en literatura científica y documentos legales.'
        );
    }
}

/**
 * Obtiene el color para el indicador de legibilidad
 */
function wordstats_pro_obtener_color_legibilidad($indice) {
    if ($indice >= 80) return '#28a745'; // Verde - Muy fácil
    if ($indice >= 60) return '#17a2b8'; // Azul - Normal
    if ($indice >= 50) return '#ffc107'; // Amarillo - Algo difícil
    return '#dc3545'; // Rojo - Difícil
}

/**
 * Calcula y muestra el tiempo estimado de lectura y estadísticas
 */
function wordstats_pro_calcula_tiempo($content) {
    if (!is_single() || get_post_type() !== 'post') {
        return $content;
    }

    // Obtener estadísticas básicas
    $palabras = str_word_count(wp_strip_all_tags($content));
    $tiempo_letra = absint(get_option('wordstats_pro_velocidad_letra', 200));
    $tiempo_estimado = max(1, ceil($palabras / $tiempo_letra));

    // Calcular índice de legibilidad
    $legibilidad = wordstats_pro_calcular_flesch($content);
    $info_nivel = wordstats_pro_obtener_descripcion_nivel($legibilidad['indice']);

    // Obtener estilos
    $tipo_fuente = sanitize_text_field(get_option('wordstats_pro_tipo_fuente', 'Arial'));
    $tamano_fuente = sanitize_text_field(get_option('wordstats_pro_tamano_fuente', '18px'));
    $color_fondo = sanitize_hex_color(get_option('wordstats_pro_fondo_color', '#f0f0f0'));
    $color_texto = sanitize_hex_color(get_option('wordstats_pro_texto_color', '#000000'));

    // Crear el indicador visual de legibilidad
    $color_indicador = wordstats_pro_obtener_color_legibilidad($legibilidad['indice']);

    // Añadir estilos y script para el tooltip
    add_action('wp_footer', 'wordstats_pro_add_tooltip_scripts');

    // Preparar el HTML
    $output = sprintf(
        '<div class="wordstats-pro-meta" style="background-color:%s; color:%s; font-family:%s; font-size:%s; text-align:center; padding:15px 10px; margin-bottom:39px;">',
        esc_attr($color_fondo),
        esc_attr($color_texto),
        esc_attr($tipo_fuente),
        esc_attr($tamano_fuente)
    );
    
    $output .= sprintf(
        '<p>Este artículo tiene %d palabras | Tiempo estimado de lectura: %d minutos</p>',
        $palabras,
        $tiempo_estimado
    );

    $output .= sprintf(
        '<div class="legibilidad-indicador" style="margin-top:10px;">
            <p>
                Índice de legibilidad Fernandez-Huerta: <span style="color:%s">%s</span> | 
                <span class="nivel-legibilidad-tooltip" data-tooltip="Rango: %s
Nivel académico: %s
%s">Nivel: %s</span>
            </p>
        </div>',
        $color_indicador,
        $legibilidad['indice'],
        esc_attr($info_nivel['rango']),
        esc_attr($info_nivel['academico']),
        esc_attr($info_nivel['descripcion']),
        $info_nivel['nivel']
    );
    
    $output .= '</div>';

    return $output . $content;
}
add_filter('the_content', 'wordstats_pro_calcula_tiempo');

/**
 * Añade los estilos y scripts necesarios para el tooltip
 */
function wordstats_pro_add_tooltip_scripts() {
    ?>
    <style>
        .nivel-legibilidad-tooltip {
            position: relative;
            cursor: help;
            border-bottom: 1px dotted #666;
        }

        .nivel-legibilidad-tooltip:before {
            content: attr(data-tooltip);
            position: absolute;
            bottom: 100%;
            left: 50%;
            transform: translateX(-50%);
            padding: 10px;
            background: #333;
            color: #fff;
            border-radius: 6px;
            font-size: 14px;
            line-height: 1.4;
            width: 300px;
            visibility: hidden;
            opacity: 0;
            transition: opacity 0.3s ease;
            white-space: pre-line;
            text-align: left;
            z-index: 1000;
            box-shadow: 0 2px 5px rgba(0,0,0,0.2);
        }

        .nivel-legibilidad-tooltip:hover:before {
            visibility: visible;
            opacity: 1;
        }

        .nivel-legibilidad-tooltip:after {
            content: "";
            position: absolute;
            bottom: 100%;
            left: 50%;
            transform: translateX(-50%);
            border: 6px solid transparent;
            border-top-color: #333;
            visibility: hidden;
            opacity: 0;
            transition: opacity 0.3s ease;
        }

        .nivel-legibilidad-tooltip:hover:after {
            visibility: visible;
            opacity: 1;
        }
    </style>
    <?php
}

/**
 * Añade la página de opciones al menú de administración
 */
function wordstats_pro_menu() {
    add_options_page(
        __('WordStats Pro', 'wordstats-pro'),
        __('WordStats Pro', 'wordstats-pro'),
        'manage_options',
        'wordstats-pro',
        'wordstats_pro_opciones_pagina'
    );
}
add_action('admin_menu', 'wordstats_pro_menu');

/**
 * Renderiza la página de opciones del plugin
 */
function wordstats_pro_opciones_pagina() {
    if (!current_user_can('manage_options')) {
        wp_die(__('No tienes permisos suficientes para acceder a esta página.'));
    }
    
    $fuentes = array('Arial', 'Verdana', 'Times New Roman', 'Georgia', 'Courier New', 'Poppins', 'Alef');
    $fuente_actual = esc_attr(get_option('wordstats_pro_tipo_fuente', 'Arial'));
    ?>
    <div class="wrap">
        <h1><?php echo esc_html(get_admin_page_title()); ?></h1>
        <form method="post" action="options.php">
            <?php
            settings_fields('wordstats_pro_opciones');
            do_settings_sections('wordstats_pro_opciones');
            ?>
            <table class="form-table">
                <tr valign="top">
                    <th scope="row"><?php _e('Velocidad de lectura (palabras por minuto):', 'wordstats-pro'); ?></th>
                    <td><input type="number" min="1" max="1000" name="wordstats_pro_velocidad_letra" 
                             value="<?php echo esc_attr(get_option('wordstats_pro_velocidad_letra', 200)); ?>" /></td>
                </tr>
                <tr valign="top">
                    <th scope="row"><?php _e('Color de fondo:', 'wordstats-pro'); ?></th>
                    <td><input type="color" name="wordstats_pro_fondo_color" 
                             value="<?php echo esc_attr(get_option('wordstats_pro_fondo_color', '#f0f0f0')); ?>" /></td>
                </tr>
                                <tr valign="top">
                    <th scope="row"><?php _e('Color del texto:', 'wordstats-pro'); ?></th>
                    <td><input type="color" name="wordstats_pro_texto_color" 
                             value="<?php echo esc_attr(get_option('wordstats_pro_texto_color', '#000000')); ?>" /></td>
                </tr>
                <tr valign="top">
                    <th scope="row"><?php _e('Tipo de fuente:', 'wordstats-pro'); ?></th>
                    <td>
                        <select name="wordstats_pro_tipo_fuente">
                            <?php 
                            foreach ($fuentes as $fuente) {
                                printf(
                                    '<option value="%s" %s>%s</option>',
                                    esc_attr($fuente),
                                    selected($fuente_actual, $fuente, false),
                                    esc_html($fuente)
                                );
                            }
                            ?>
                        </select>
                    </td>
                </tr>
                <tr valign="top">
                    <th scope="row"><?php _e('Tamaño de fuente:', 'wordstats-pro'); ?></th>
                    <td><input type="text" name="wordstats_pro_tamano_fuente" 
                             value="<?php echo esc_attr(get_option('wordstats_pro_tamano_fuente', '18px')); ?>" /></td>
                </tr>
            </table>
            <?php submit_button(); ?>
        </form>
    </div>
    <?php
}

/**
 * Registra las opciones del plugin
 */
function wordstats_pro_registrar_opciones() {
    register_setting('wordstats_pro_opciones', 'wordstats_pro_velocidad_letra', 'absint');
    register_setting('wordstats_pro_opciones', 'wordstats_pro_fondo_color', 'sanitize_hex_color');
    register_setting('wordstats_pro_opciones', 'wordstats_pro_texto_color', 'sanitize_hex_color');
    register_setting('wordstats_pro_opciones', 'wordstats_pro_tipo_fuente', 'sanitize_text_field');
    register_setting('wordstats_pro_opciones', 'wordstats_pro_tamano_fuente', 'sanitize_text_field');
}
add_action('admin_init', 'wordstats_pro_registrar_opciones');

/**
 * Añadir CSS personalizado adicional para mejorar la presentación
 */
function wordstats_pro_styles() {
    ?>
    <style>
        .wordstats-pro-meta {
            border-radius: 5px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            margin: 20px 0;
        }
        .legibilidad-indicador {
            padding: 10px;
            border-top: 1px solid rgba(0,0,0,0.1);
            margin-top: 10px;
        }
        .legibilidad-indicador p {
            margin: 5px 0;
        }
        /* Estilos adicionales para mejorar la responsividad del tooltip */
        @media screen and (max-width: 600px) {
            .nivel-legibilidad-tooltip:before {
                width: 250px;
                font-size: 12px;
                left: 0;
                transform: none;
            }
            .nivel-legibilidad-tooltip:after {
                left: 20px;
                transform: none;
            }
        }
        /* Mejora de la accesibilidad para el hover en dispositivos táctiles */
        @media (hover: none) {
            .nivel-legibilidad-tooltip:active:before,
            .nivel-legibilidad-tooltip:active:after {
                visibility: visible;
                opacity: 1;
            }
        }
    </style>
    <?php
}
add_action('wp_head', 'wordstats_pro_styles');
add_action('admin_head', 'wordstats_pro_styles');

/**
 * Función de activación del plugin
 */
function wordstats_pro_activar() {
    // Establecer valores por defecto
    add_option('wordstats_pro_velocidad_letra', 200);
    add_option('wordstats_pro_fondo_color', '#f0f0f0');
    add_option('wordstats_pro_texto_color', '#000000');
    add_option('wordstats_pro_tipo_fuente', 'Arial');
    add_option('wordstats_pro_tamano_fuente', '18px');
}
register_activation_hook(__FILE__, 'wordstats_pro_activar');

/**
 * Función de desactivación del plugin
 */
function wordstats_pro_desactivar() {
    // Limpieza si es necesaria
}
register_deactivation_hook(__FILE__, 'wordstats_pro_desactivar');

/**
 * Función de desinstalación del plugin
 */
function wordstats_pro_desinstalar() {
    // Eliminar opciones
    delete_option('wordstats_pro_velocidad_letra');
    delete_option('wordstats_pro_fondo_color');
    delete_option('wordstats_pro_texto_color');
    delete_option('wordstats_pro_tipo_fuente');
    delete_option('wordstats_pro_tamano_fuente');
}
register_uninstall_hook(__FILE__, 'wordstats_pro_desinstalar');