<?php

use StreetMesh\Protocol\Laravel\Records\Record;

/*
|--------------------------------------------------------------------------
| What this server keeps, and who may see it
|--------------------------------------------------------------------------
|
| Only the parts this application decides. Everything else comes from the
| package's own config and does not need repeating here.
|
*/

return [

    /*
     |--------------------------------------------------------------------------
     | Collections
     |--------------------------------------------------------------------------
     |
     | The kinds of record this server is willing to hold, and whether each is
     | public. Visibility belongs to the collection rather than to the record,
     | because a per-record setting is an input, and an input can be wrong,
     | forged, or flipped by a bug in a form. Publishing cannot be undone.
     |
     | A collection absent from this list is refused rather than given a
     | default, so a typo in a record type is a failure instead of a new kind of
     | record nobody meant to create.
     |
     | Chess is declared here for now because it is what the delegation check
     | writes. It belongs to the experience package that defines it, and should
     | move there once `Laravel-Chess` exists and capabilities can contribute
     | their own collections.
     |
     */

    'collections' => [
        'com.streetmesh.games.chess' => Record::PUBLIC,
    ],

];
