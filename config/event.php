<?php
return [
    'events' => [
        'view_parse'   => [
            'Think\Event\ParseTemplate'
        ],
        'view_compile' => [
            'Think\Event\ContentReplace'
        ]
    ]
];