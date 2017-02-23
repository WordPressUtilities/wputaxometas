Taxo Metas
=================

[![Build Status](https://travis-ci.org/WordPressUtilities/wputaxometas.svg?branch=master)](https://travis-ci.org/WordPressUtilities/wputaxometas)

Add extra fields to the taxonomy administration.

How to install :
---

* Put this folder to your wp-content/plugins/ folder.
* Activate the plugin in "Plugins" admin section.

How to add fields :
---

Put the code below in your theme's functions.php file. Add new fields to your convenance.

```php
add_filter( 'wputaxometas_fields', 'set_wputaxometas_fields' );
function set_wputaxometas_fields( $fields ) {
    $fields['category_long_description'] = array(
        'label' => 'Test field',
        'taxonomies' => array( 'category' ),
        'description' => 'a long description',
        'type' => 'textarea'
    );
    return $fields;
}
```

Fields parameters :
---

* "label" : String (optional) / Add a label to the field administration. Default to ID value.
* "long_label" : String (optional) / Add a long label next to a checkbox. Default to label value.
* "taxonomies" : Array (optional) / Set the taxonomies for which the meta will be used. Default to array( 'category' )
* "description" : String (optional) / Add a long description to the field administration to help the user in filling this field.
* "post_type" : String (optional) / Add a post type to target. Default to "post".
* "taxonomy_type" : String (optional) / Add a taxonomy type to target. Default to "category".
* "datas" : Array (optional) / Datas to use in select/radio. Default to array('No','Yes').
* "type" : String (optional) / Set a kind of form field. Default to "text".
* "column" : Bool (optional) / Display in taxo admin as a column. Default to false.

Fields types :
---

* "taxonomy" : select between terms in a taxonomy.
* "post" : select between posts.
* "text" : input type text.
* "email" : input type email.
* "url" : input type url.
* "color" : input type color.
* "number" : input type number.
* "checkbox" : input type checkbox.
* "radio" : radio between datas.
* "select" : select between datas.
* "textarea" : textarea field.
* "editor" : the WYSIWYG editor used in the content of a post.
* "attachment" : An attached image (only the ID of the attachment is stored)
