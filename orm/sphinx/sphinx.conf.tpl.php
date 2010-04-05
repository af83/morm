#
# Sphinx configuration file
#

<?php 
    $cpt = 0;
foreach($this->sources as $source) { 
    ?>

#############################################################################
## data source definition for <?php echo $source->index_name."\n"; ?>
#############################################################################

source <?php echo $source->index_name; ?>

{
	type					= <?php echo $source->type."\n"; ?>
	
    # some straightforward parameters for SQL source types
	sql_host				= <?php echo $source->sql_host."\n"; ?>
	sql_user				= <?php echo $source->sql_user."\n"; ?>
	sql_pass				= <?php echo $source->sql_pass."\n"; ?>
	sql_db					= <?php echo $source->sql_db."\n"; ?>
	sql_port				= <?php echo $source->sql_port."\n"; ?>	# optional, default is 3306
	# sql_sock				= /tmp/mysql.sock


	# MySQL specific client connection flags
	# optional, default is 0
	#
	# mysql_connect_flags	= 32 # enable compression

	# pre-query, executed before the main fetch query
	#
    <?php foreach($source->pre_queries as $query) { ?>
        sql_query_pre			= <?php echo $query."\n"; ?>
    <?php } ?>

	# fetch query
	sql_query				= <?php echo sprintf($source->fetch_query, $this->sources_count, $cpt)."\n"; ?>
	
    <?php foreach($source->attributes as $attr) { ?>
            <?php echo $attr['type']; ?> = <?php echo $attr['name']."\n"; ?>
    <?php } ?>

	sql_ranged_throttle	= 0

    sql_query_range = <?php echo $source->query_range; ?>
	
	# document info query, ONLY for CLI search (ie. testing and debugging)
	# optional, default is empty
	sql_query_info		= <?php echo $source->cli_query."\n"; ?>
}


#############################################################################
## index definition for <?php echo $source->index_name."\n"; ?>
#############################################################################

# this is an index which is stored locally in the filesystem
#
index <?php echo $source->index_name; ?>_index
{
	# document source(s) to index
	# multi-value, mandatory
	# document IDs must be globally unique across all sources
	    source			= <?php echo $source->index_name."\n"; ?>

	# index files path and file name, without extension
	# mandatory, path must be writable, extensions will be auto-appended
	path			= <?php echo $this->global_conf['index_dir'] ?>/<?php echo $source->index_name; ?>_index

	# document attribute values (docinfo) storage mode
	# optional, default is 'extern'
	# known values are 'none', 'extern' and 'inline'
	docinfo			= extern

	# memory locking for cached data (.spa and .spi), to prevent swapping
	# optional, default is 0 (do not mlock)
	# requires searchd to be run from root
	mlock			= 0

	morphology		= none

	# minimum indexed word length
	# default is 1 (index everything)
	min_word_len		= 1

	# charset encoding type
	charset_type		= utf-8

	# charset definition and case folding rules "table"
	# optional, default value depends on charset_type
	#
	# defaults are configured to include English and Russian characters only
	# you need to change the table to include additional ones
	# this behavior MAY change in future versions
	#
	# 'sbcs' default value is
	# charset_table		= 0..9, A..Z->a..z, _, a..z, U+A8->U+B8, U+B8, U+C0..U+DF->U+E0..U+FF, U+E0..U+FF
	#
	# 'utf-8' default value is
	# charset_table		= 0..9, A..Z->a..z, _, a..z, U+410..U+42F->U+430..U+44F, U+430..U+44F


	# minimum word infix length to index
    # required by star-syntax
	#
	min_infix_len		= 1

	# enable star-syntax (wildcards) when searching prefix/infix indexes
	# known values are 0 and 1
	# optional, default is 0 (do not use wildcard syntax)
	#
	enable_star		= 1

	# n-gram length to index, for CJK indexing
	# only supports 0 and 1 for now, other lengths to be implemented
	# optional, default is 0 (disable n-grams)
	#
	# ngram_len				= 1

	# whether to strip HTML tags from incoming documents
	# known values are 0 (do not strip) and 1 (do strip)
	# optional, default is 0
	html_strip				= 1
}

index <?php echo $source->index_name; ?>_index_d
{
  type = distributed
  local = <?php echo $source->index_name; ?>_index
}

<?php 
$cpt++;
} ?>

#############################################################################
## indexer settings
#############################################################################

indexer
{
	# memory limit, in bytes, kiloytes (16384K) or megabytes (256M)
	# optional, default is 32M, max is 2047M, recommended is 256M to 1024M
	mem_limit			= 32M
}

#############################################################################
## searchd settings
#############################################################################

searchd
{
	# IP address to bind on
	# optional, default is 0.0.0.0 (ie. listen on all interfaces)
	#
	# address				= 127.0.0.1
	# address				= 192.168.0.1
    listen              = <?php echo isset($this->global_conf['address']) ? $this->global_conf['address'] : '0.0.0.0' ?>

	# searchd TCP port number
	# mandatory, default is 3312
	port				= <?php echo isset($this->global_conf['port']) ? $this->global_conf['port'] : '3312' ?>

	# log file, searchd run info is logged here
	# optional, default is 'searchd.log'
	log					= <?php echo $this->global_conf['log_dir'] ?>/searchd.log

	# query log file, all search queries are logged here
	# optional, default is empty (do not log queries)
	query_log			= <?php echo $this->global_conf['log_dir'] ?>/query.log

	# client read timeout, seconds
	# optional, default is 5
	read_timeout		= 5

	# maximum amount of children to fork (concurrent searches to run)
	# optional, default is 0 (unlimited)
	max_children		= 30

	# PID file, searchd process ID file name
	# mandatory
	pid_file			= <?php echo $this->global_conf['log_dir'] ?>/searchd.pid

	# max amount of matches the daemon ever keeps in RAM, per-index
	# WARNING, THERE'S ALSO PER-QUERY LIMIT, SEE SetLimits() API CALL
	# default is 1000 (just like Google)
	max_matches			= 1000

	# seamless rotate, prevents rotate stalls if precaching huge datasets
	# optional, default is 1
	seamless_rotate		= 1

	# whether to forcibly preopen all indexes on startup
	# optional, default is 0 (do not preopen)
	preopen_indexes		= 0

	# whether to unlink .old index copies on succesful rotation.
	# optional, default is 1 (do unlink)
	unlink_old			= 1
}

# --eof--

