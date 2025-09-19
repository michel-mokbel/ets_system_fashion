<?php
// Initialize language settings
$available_languages = [];
$translations = [];

// Load language definitions
$languages_file = __DIR__ . '/languages.json';
if (file_exists($languages_file)) {
    $available_languages = json_decode(file_get_contents($languages_file), true);
} else {
    // Fallback if languages file is missing
    $available_languages = [
        'en' => [
            'name' => 'English',
            'flag' => 'gb'
        ],
        'fr' => [
            'name' => 'Français',
            'flag' => 'fr'
        ]
    ];
}

// Set default language
if (!isset($_SESSION['lang'])) {
    $_SESSION['lang'] = 'en';
}

// Handle language switch
if (isset($_GET['lang']) && in_array($_GET['lang'], array_keys($available_languages))) {
    $_SESSION['lang'] = $_GET['lang'];
}

// Load translations
$translations_file = __DIR__ . '/translations.json';
if (file_exists($translations_file)) {
    $all_translations = json_decode(file_get_contents($translations_file), true);
    $current_lang = $_SESSION['lang'];
    
    if (isset($all_translations[$current_lang])) {
        $translations = $all_translations[$current_lang];
    }
} else {
    // Fallback for basic translations if file is missing
    $translations = [
        'site.title' => 'Inventory & Maintenance Management System',
        'login.title' => 'Inventory System',
        'login.subtitle' => 'Please sign in to continue',
        'login.username' => 'Username',
        'login.password' => 'Password',
        'login.submit' => 'Sign In',
        'login.error' => 'Invalid username or password'
    ];
}

// Make sure these functions are defined
if (!function_exists('getTranslation')) {
    function getTranslation($key) {
        global $translations;
        
        if (isset($translations[$key])) {
            return $translations[$key];
        }
        
        return $key;
    }
}

if (!function_exists('language_switcher')) {
    function language_switcher() {
        global $available_languages;
        
        $current_lang = $_SESSION['lang'] ?? 'en';
        $output = '<div class="language-switcher">';
        
        foreach ($available_languages as $code => $language) {
            $active = ($current_lang == $code) ? 'active' : '';
            $output .= '<a href="?lang=' . $code . '" class="lang-flag ' . $active . '" title="' . $language['name'] . '">';
            $output .= '<span class="flag-icon flag-icon-' . $language['flag'] . '"></span>';
            $output .= '</a>';
        }
        
        $output .= '</div>';
        return $output;
    }
}
?> 