# Configuration et Dépannage - Frontend UFSC

## Checklist de Configuration

### 1. Prérequis ✅

- [ ] WordPress 6.0+ installé et fonctionnel
- [ ] WooCommerce plugin activé et configuré
- [ ] PHP 7.4+ (recommandé 8.0+)
- [ ] Permissions fichiers correctes (755 pour dossiers, 644 pour fichiers)

### 2. Configuration WooCommerce ✅

Aller dans **UFSC Gestion → WooCommerce** et vérifier:

- [ ] **Produit Pack d'affiliation** configuré (ID: 4823 par défaut)
- [ ] **Produit Licence additionnelle** configuré (ID: 2934 par défaut)
- [ ] **Quota de licences incluses** défini (10 par défaut)
- [ ] **Saison par défaut** configurée (2025-2026)

### 3. Test des Shortcodes ✅

Créer une page de test et ajouter:

```
[ufsc_club_dashboard]
```

Vérifier que:
- [ ] La page charge sans erreur
- [ ] Les styles CSS sont appliqués
- [ ] Le JavaScript fonctionne (navigation par onglets)
- [ ] Les messages d'erreur appropriés s'affichent (si pas connecté)

### 4. Test des Permissions ✅

Avec un compte utilisateur test:

- [ ] **Non connecté**: Message d'erreur approprié affiché
- [ ] **Connecté sans club**: Message "Aucun club associé" affiché
- [ ] **Connecté avec club**: Tableau de bord fonctionnel

### 5. Test de l'API REST ✅

Tester manuellement ou via browser developer tools:

```bash
# Test endpoint stats (doit nécessiter authentification)
curl -X GET "https://yoursite.com/wp-json/ufsc/v1/stats" \
     -H "X-WP-Nonce: YOUR_NONCE"
```

- [ ] **Sans auth**: Erreur 401/403
- [ ] **Avec auth valide**: Données retournées
- [ ] **Nonce invalide**: Erreur de sécurité

### 6. Test d'Upload de Logo ✅

- [ ] Upload de fichier JPG/PNG fonctionne
- [ ] Validation de taille (2MB max) active
- [ ] Prévisualisation affichée correctement
- [ ] Suppression de logo fonctionne

### 7. Test d'Export/Import ✅

- [ ] Export CSV téléchargeable
- [ ] Export Excel (si PhpSpreadsheet disponible)
- [ ] Import CSV avec prévisualisation
- [ ] Validation des erreurs dans CSV

### 8. Test des Notifications ✅

- [ ] Email envoyé lors de création de licence
- [ ] Email envoyé lors de validation
- [ ] Templates email correctement formatés

### 9. Test du Journal d'Audit ✅

- [ ] Menu **UFSC Gestion → Audit** accessible (admin uniquement)
- [ ] Actions enregistrées dans le journal
- [ ] Nettoyage automatique configuré

---

## Dépannage Courant

### ❌ Les shortcodes n'affichent rien

**Causes possibles:**
1. Plugin non activé correctement
2. Erreur PHP fatale
3. Conflit de thème/plugin

**Solutions:**
```bash
# Vérifier les logs d'erreur
tail -f /path/to/wordpress/wp-content/debug.log

# Activer le mode debug
# Dans wp-config.php:
define('WP_DEBUG', true);
define('WP_DEBUG_LOG', true);
```

**Vérification manuelle:**
```php
// Tester en ajoutant dans functions.php
add_action('wp_footer', function() {
    if (shortcode_exists('ufsc_club_dashboard')) {
        echo "<!-- UFSC shortcodes registered -->";
    } else {
        echo "<!-- UFSC shortcodes NOT registered -->";
    }
});
```

### ❌ Erreur "Class not found"

**Cause:** Fichiers non inclus correctement

**Solution:**
```php
// Vérifier dans ufsc-clubs-licences-sql.php que tous les require_once sont présents:
require_once UFSC_CL_DIR.'includes/frontend/class-frontend-shortcodes.php';
require_once UFSC_CL_DIR.'includes/api/class-rest-api.php';
// etc...
```

### ❌ CSS/JS ne se chargent pas

**Causes possibles:**
1. Assets non enqueue correctement
2. Chemin de fichier incorrect
3. Cache navigateur/plugin

**Solutions:**
```php
// Forcer le rechargement
add_action('wp_enqueue_scripts', function() {
    wp_enqueue_style('ufsc-frontend', 
        plugin_dir_url(__FILE__) . 'assets/frontend/css/frontend.css', 
        array(), 
        time() // Force reload
    );
});
```

### ❌ API REST retourne 404

**Causes possibles:**
1. Permaliens non rafraîchis
2. Routes non enregistrées
3. Conflit .htaccess

**Solutions:**
```bash
# Rafraîchir les permaliens
wp rewrite flush

# Ou via admin WordPress:
# Réglages → Permaliens → Enregistrer
```

### ❌ Erreurs de permissions

**Cause:** Fonction stub non implémentée

**Solution temporaire:**
```php
// Dans votre thème ou plugin personnalisé:
if (!function_exists('ufsc_get_user_club_id')) {
    function ufsc_get_user_club_id($user_id) {
        // TODO: Implémenter selon votre schéma DB
        return 1; // Club de test
    }
}
```

### ❌ Upload de logo échoue

**Causes possibles:**
1. Permissions dossier uploads
2. Taille fichier trop importante
3. Type MIME non autorisé

**Solutions:**
```bash
# Vérifier permissions
chmod 755 wp-content/uploads/
chmod 644 wp-content/uploads/*

# Vérifier configuration PHP
php -i | grep upload_max_filesize
php -i | grep post_max_size
```

### ❌ Emails non envoyés

**Causes possibles:**
1. Serveur mail non configuré
2. Plugin SMTP requis
3. Fonction wp_mail() bloquée

**Solutions:**
```php
// Tester wp_mail()
wp_mail('test@example.com', 'Test', 'Corps du message');

// Ou installer un plugin SMTP comme WP Mail SMTP
```

### ❌ Cache stats ne fonctionne pas

**Cause:** Transients non supportés ou supprimés

**Solution:**
```php
// Vérifier support transients
if (get_transient('test_key') === false) {
    set_transient('test_key', 'test_value', 60);
    if (get_transient('test_key') === 'test_value') {
        echo "Transients fonctionnent";
    }
}
```

---

## Optimisation des Performances

### 1. Cache Avancé 🚀

```php
// Utiliser cache objet si disponible
if (wp_using_ext_object_cache()) {
    wp_cache_set($key, $data, 'ufsc_group', 3600);
    $data = wp_cache_get($key, 'ufsc_group');
}
```

### 2. Optimisation des Requêtes 📊

```php
// Utiliser WP_Query avec cache
$clubs_query = new WP_Query(array(
    'post_type' => 'club',
    'posts_per_page' => 20,
    'meta_query' => array(
        'relation' => 'AND',
        // Requêtes optimisées...
    ),
    'cache_results' => true,
    'update_post_meta_cache' => false // Si pas besoin des metas
));
```

### 3. Minification Assets 📦

```bash
# Utiliser des outils de build
npm install -g uglify-js clean-css-cli

# Minifier CSS
cleancss -o frontend.min.css frontend.css

# Minifier JS  
uglifyjs frontend.js -c -m -o frontend.min.js
```

### 4. Chargement Conditionnel ⚡

```php
// Charger assets seulement si nécessaire
add_action('wp_enqueue_scripts', function() {
    global $post;
    
    // Seulement sur pages avec shortcodes UFSC
    if (!$post || !has_shortcode($post->post_content, 'ufsc_')) {
        return;
    }
    
    wp_enqueue_style('ufsc-frontend');
    wp_enqueue_script('ufsc-frontend');
});
```

---

## Monitoring et Maintenance

### 1. Logs d'Audit 📋

Surveiller régulièrement:
- Actions suspectes
- Tentatives d'accès non autorisées
- Erreurs répétées

```bash
# Via WP-CLI
wp ufsc audit list --limit=50 --format=table
wp ufsc audit stats
```

### 2. Nettoyage Automatique 🧹

```php
// Planifier nettoyage mensuel
if (!wp_next_scheduled('ufsc_monthly_cleanup')) {
    wp_schedule_event(time(), 'monthly', 'ufsc_monthly_cleanup');
}

add_action('ufsc_monthly_cleanup', function() {
    // Nettoyer logs > 1 an
    UFSC_Audit_Logger::cleanup_old_logs(365);
    
    // Nettoyer transients expirés
    wp_cache_flush();
    
    // Optimiser base de données
    $GLOBALS['wpdb']->query("OPTIMIZE TABLE {$GLOBALS['wpdb']->posts}");
});
```

### 3. Alertes de Sécurité 🔐

```php
// Alerter sur actions sensibles
add_action('ufsc_licence_validated', function($licence_id, $club_id) {
    if (current_user_can('manage_options')) {
        return; // Admin normal
    }
    
    // Alerter si validation par non-admin
    wp_mail(
        get_option('admin_email'),
        'Alerte: Validation licence par utilisateur',
        "Licence {$licence_id} validée par " . wp_get_current_user()->user_login
    );
}, 10, 2);
```

### 4. Sauvegarde de Configuration 💾

```bash
# Sauvegarder configuration WooCommerce UFSC
wp option get ufsc_woocommerce_settings > ufsc_config_backup.json

# Restaurer si nécessaire
wp option update ufsc_woocommerce_settings "$(cat ufsc_config_backup.json)"
```

---

## Support et Documentation

### Ressources Utiles 📚

- **WordPress Codex**: https://codex.wordpress.org/
- **WooCommerce Docs**: https://docs.woocommerce.com/
- **REST API Handbook**: https://developer.wordpress.org/rest-api/

### Contacts de Support 📞

Pour des problèmes spécifiques au développement:
1. Vérifier les logs d'erreur WordPress
2. Tester avec thème par défaut (Twenty Twenty-Four)
3. Désactiver autres plugins temporairement
4. Contacter l'équipe de développement avec:
   - Version WordPress/PHP
   - Messages d'erreur complets
   - Étapes pour reproduire le problème

### Debug Mode Avancé 🔍

```php
// Activer debug complet dans wp-config.php
define('WP_DEBUG', true);
define('WP_DEBUG_LOG', true);
define('WP_DEBUG_DISPLAY', false);
define('SCRIPT_DEBUG', true);
define('SAVEQUERIES', true);

// Logger spécifique UFSC
if (!function_exists('ufsc_debug_log')) {
    function ufsc_debug_log($message) {
        if (WP_DEBUG === true) {
            if (is_array($message) || is_object($message)) {
                error_log('[UFSC] ' . print_r($message, true));
            } else {
                error_log('[UFSC] ' . $message);
            }
        }
    }
}
```