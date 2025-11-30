<?php
// includes/logic/i18n_server.php

class I18n {
    private static $translations = [];
    private static $currentLang = 'es-latam';
    private static $loaded = false;

    public static function load($lang) {
        // Evitar recarga si ya está cargado el mismo idioma
        if (self::$loaded && self::$currentLang === $lang) return;

        self::$currentLang = $lang;
        
        // Ruta relativa desde includes/logic/ hacia public/assets/translations/
        $filePath = __DIR__ . '/../../public/assets/translations/' . $lang . '.json';
        
        // [CORRECCIÓN] Eliminamos el fallback a es-latam.
        // Si el archivo no existe, $translations se queda vacío y la función get()
        // devolverá la 'clave' tal cual, que es el comportamiento que esperas.
        
        /* BLOQUE ELIMINADO:
        if (!file_exists($filePath)) {
            $filePath = __DIR__ . '/../../public/assets/translations/es-latam.json';
        }
        */

        if (file_exists($filePath)) {
            $json = file_get_contents($filePath);
            self::$translations = json_decode($json, true) ?? [];
        } else {
            // Si no existe el archivo, dejamos el array vacío.
            self::$translations = [];
        }
        
        self::$loaded = true;
    }

    public static function get($key, $vars = []) {
        $keys = explode('.', $key);
        $current = self::$translations;

        foreach ($keys as $k) {
            if (isset($current[$k])) {
                $current = $current[$k];
            } else {
                return $key; // Si no encuentra la traducción, devuelve la CLAVE
            }
        }

        // Si es un string, reemplazamos variables
        if (is_string($current)) {
            foreach ($vars as $variable => $value) {
                $current = str_replace('{' . $variable . '}', $value, $current);
            }
            return $current;
        }

        return $key;
    }
}

// Helper function global
if (!function_exists('translation')) {
    function translation($key, $vars = []) {
        return I18n::get($key, $vars);
    }
}
?>