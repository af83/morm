<?php

class MorphinxSearch
{

    const CONF_FILE = '/conf/sphinx.php';

    private $client;

    private $args = array();

    private $conf = NULL;

    public function __construct($args = array())
    {
        $this->args = $args;
        $this->client = new SphinxClient();
        $this->setClient();
    }

    public function search($pattern)
    {
        $conf = $this->getConf();
        return $this->client->Query($pattern, $conf['index'] );
    }

    private function getConf()
    {
        if(is_null($this->conf))
        {
            if(isset($this->args['conf']))
                $this->conf = array_merge($this->getDefaultConf(), $this->args['conf']);
            else
                $this->conf = $this->getDefaultConf();
        }
        return $this->conf;
    }

    private function getDefaultConf()
    {
        /**
         * $sphinx_conf could be in the CONF_FILE and included dynamically 
         */
        //require_once(BASE_PATH . self::CONF_FILE);
        $sphinx_conf = array(
                             'host'=> "localhost",
                             'port'=> 3312,
                             'connect_timeout' => 1,
                             'weights' => array ( 100, 1 ),
                             'ranker' => SPH_RANK_PROXIMITY_BM25,
                             'index' => "*",
                             'match_mode'=> SPH_MATCH_ALL,
                            );
        return $sphinx_conf;
    }

    private function setClient()
    {
        $conf = $this->getConf();
        $this->client->SetServer($conf['host'], $conf['port'] );
        $this->client->SetArrayResult(true );
        if(isset($this->args['pagination']))
        {
            $offset = isset($this->args['pagination'][0]) ? $this->args['pagination'][0] : 0;
            $limit = isset($this->args['pagination'][1]) ? $this->args['pagination'][1] : 20;
            $this->client->SetLimits($offset, $limit);
        }
        if(isset($this->args['weights']))
            $this->client->SetFieldWeights( $this->args['weights'] );
        if(isset($this->args['conditions']))
        {
            foreach($this->args['conditions'] as $attr_name => $filter)
            {
                $this->client->SetFilter($attr_name, is_array($filter) ? $filter : array($filter) );
            }
        }
        $this->client->SetMatchMode ( isset($conf['match_mode']) ? $conf['match_mode'] : SPH_MATCH_ALL );
        $mode = isset($conf['sort_mode']) && isset($conf['sort_mode'][0]) ? $conf['sort_mode'][0] : SPH_SORT_RELEVANCE;
        $groupby = isset($conf['sort_mode']) && isset($conf['sort_mode'][1]) ? $conf['sort_mode'][1] : '';
        $this->client->SetSortMode($mode, $groupby);
        if (isset($this->args['filters'])) 
        {
            foreach ($this->args['filters'] as $col => $crit)
            {
                if (!is_array($crit))
                {
                    $crit = array($crit);
                }
                $this->client->SetFilter($col, $crit);
            }
        }
        //$this->client->SetIndexWeights ( array ( "sfrjt_user_User_index" => 2, "sfrjt_user_Artist_index" => 3 ) );
        //$this->client->SetConnectTimeout ( $conf['connect_timeout'] );
        //$this->client->SetWeights ( $conf['weights'] );
        //if ( count($filtervals) )	$cl->SetFilter ( $filter, $filtervals );
        //if ( $groupby )				$cl->SetGroupBy ( $groupby, SPH_GROUPBY_ATTR, $groupsort );
        //if ( $sortby )				$cl->SetSortMode ( SPH_SORT_EXTENDED, $sortby );
        //if ( $sortexpr )			$cl->SetSortMode ( SPH_SORT_EXPR, $sortexpr );
        //if ( $distinct )			$cl->SetGroupDistinct ( $distinct );
        //if ( $limit )				$cl->SetLimits ( 0, $limit, ( $limit>1000 ) ? $limit : 1000 );
        //$this->client->SetRankingMode ( $conf['ranker'] );
    }

}
