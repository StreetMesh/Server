<?php

return [

    /*
     |--------------------------------------------------------------------------
     | The front page
     |--------------------------------------------------------------------------
     |
     | What a stranger sees at the root, before signing in. A server has one
     | root, so if it offers more than one capability it has to say which one
     | greets people. Null follows whatever is installed, which is enough for a
     | server offering only one thing.
     |
     */

    'front_page' => env('STREETMESH_FRONT_PAGE'),

    /*
     |--------------------------------------------------------------------------
     | The home page
     |--------------------------------------------------------------------------
     |
     | What somebody signed in sees. The one surface where two installed
     | capabilities genuinely overlap, so it is a collection of panels rather
     | than a page either of them owns.
     |
     | Null shows everything on offer, in the order capabilities were
     | registered. Naming them instead is how an operator decides what their
     | server is for — and a name nothing provides is skipped rather than fatal,
     | so removing a package does not break a page.
     |
     */

    'home_page' => null,

    'network' => [
        'timeout' => 10,
        'cache_seconds' => 300,
    ],

];
