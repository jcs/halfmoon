<?php
namespace ActiveRecord;

class MysqlAdapter extends Connection
{
	public function default_port()
	{
		return 3306;
	}

	public function limit($sql, $offset, $limit)
	{
		$offset = intval($offset);
		$limit = intval($limit);
		return "$sql LIMIT $offset,$limit";
	}

	public function query_column_info($table)
	{
		return $this->query("SHOW COLUMNS FROM $table");
	}

	public function query_for_tables()
	{
		return $this->query('SHOW TABLES');
	}

	public function quote_name($string)
	{
		return "`$string`";
	}

	public function create_column(&$column)
	{
		$c = new Column();
		$c->inflected_name	= Inflector::instance()->variablize($column['field']);
		$c->name			= $column['field'];
		$c->nullable		= ($column['null'] === 'YES' ? true : false);
		$c->pk				= ($column['key'] === 'PRI' ? true : false);
		$c->auto_increment	= ($column['extra'] === 'auto_increment' ? true : false);

		if ($column['type'] == 'timestamp' || $column['type'] == 'datetime')
		{
			$c->raw_type = 'datetime';
			$c->length = 19;
		}
		elseif ($column['type'] == 'date')
		{
			$c->raw_type = 'date';
			$c->length = 10;
		}
		else
		{
			preg_match('/^(.*?)\(([0-9]+(,[0-9]+)?)\)/',$column['type'],$matches);

			if (sizeof($matches) > 0)
			{
				$c->raw_type = $matches[1];
				$c->length = intval($matches[2]);
			}
		}

		$c->map_raw_type();
		$c->default = $c->cast($column['default']);

		return $c;
	}
}
?>
