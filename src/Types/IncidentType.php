<?php
namespace abrain\Einsatzverwaltung\Types;

/**
 * Description of the custom taxonomy 'Type of incident'
 * @package abrain\Einsatzverwaltung\Types
 */
class IncidentType implements CustomType
{
    /**
     * @return string
     */
    public function getSlug()
    {
        return 'einsatzart';
    }

    /**
     * @return array
     */
    public function getRegistrationArgs()
    {
        return array(
            'label' => 'Einsatzarten',
            'labels' => array(
                'name' => 'Einsatzarten',
                'singular_name' => 'Einsatzart',
                'menu_name' => 'Einsatzarten',
                'search_items' => 'Einsatzarten suchen',
                'popular_items' => 'H&auml;ufige Einsatzarten',
                'all_items' => 'Alle Einsatzarten',
                'parent_item' => '&Uuml;bergeordnete Einsatzart',
                'parent_item_colon' => '&Uuml;bergeordnete Einsatzart:',
                'edit_item' => 'Einsatzart bearbeiten',
                'view_item' => 'Einsatzart ansehen',
                'update_item' => 'Einsatzart aktualisieren',
                'add_new_item' => 'Neue Einsatzart',
                'new_item_name' => 'Einsatzart hinzuf&uuml;gen',
                'separate_items_with_commas' => 'Einsatzarten mit Kommas trennen',
                'add_or_remove_items' => 'Einsatzarten hinzuf&uuml;gen oder entfernen',
                'choose_from_most_used' => 'Aus h&auml;ufigen Einsatzarten w&auml;hlen',
                'not_found' => 'Keine Einsatzarten gefunden.',
                'no_terms' => 'Keine Einsatzarten',
                'items_list_navigation' => 'Navigation der Liste der Einsatzarten',
                'items_list' => 'Liste der Einsatzarten',
            ),
            'public' => true,
            'show_in_nav_menus' => false,
            'meta_box_cb' => 'abrain\Einsatzverwaltung\Admin::displayMetaBoxEinsatzart',
            'capabilities' => array(
                'manage_terms' => 'edit_einsatzberichte',
                'edit_terms' => 'edit_einsatzberichte',
                'delete_terms' => 'edit_einsatzberichte',
                'assign_terms' => 'edit_einsatzberichte'
            ),
            'hierarchical' => true
        );
    }
}
