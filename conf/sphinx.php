<?php

$sphinx_conf = array(
                     'host'=> "localhost",
                     'port'=> 3312,
                     'connect_timeout' => 1,

                     'weights' => array ( 100, 1 ),
                     'ranker' => SPH_RANK_PROXIMITY_BM25,
                     'index' => "*",
                     'mode'=> SPH_MATCH_ALL,
                     );
