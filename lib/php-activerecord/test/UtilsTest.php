<?php
include 'helpers/config.php';

class UtilsTest extends SnakeCasePHPUnitMethodNames
{
	public function setUp()
	{
		$this->object_array = array(null,null);
		$this->object_array[0] = new stdClass();
		$this->object_array[0]->a = "0a";
		$this->object_array[0]->b = "0b";
		$this->object_array[1] = new stdClass();
		$this->object_array[1]->a = "1a";
		$this->object_array[1]->b = "1b";

		$this->array_hash = array(
			array("a" => "0a", "b" => "0b"),
			array("a" => "1a", "b" => "1b"));
	}

	public function testCollectWithArrayOfObjectsUsingClosure()
	{
		$this->assertEquals(array("0a","1a"),ActiveRecord\collect($this->object_array,function($obj) { return $obj->a; }));
	}

	public function testCollectWithArrayOfObjectsUsingString()
	{
		$this->assertEquals(array("0a","1a"),ActiveRecord\collect($this->object_array,"a"));
	}

	public function testCollectWithArrayHashUsingClosure()
	{
		$this->assertEquals(array("0a","1a"),ActiveRecord\collect($this->array_hash,function($item) { return $item["a"]; }));
	}

	public function testCollectWithArrayHashUsingString()
	{
		$this->assertEquals(array("0a","1a"),ActiveRecord\collect($this->array_hash,"a"));
	}

    public function testArrayFlatten()
    {
		$this->assertEquals(array(), ActiveRecord\array_flatten(array()));
		$this->assertEquals(array(1), ActiveRecord\array_flatten(array(1)));
		$this->assertEquals(array(1), ActiveRecord\array_flatten(array(array(1))));
		$this->assertEquals(array(1, 2), ActiveRecord\array_flatten(array(array(1, 2))));
		$this->assertEquals(array(1, 2), ActiveRecord\array_flatten(array(array(1), 2)));
		$this->assertEquals(array(1, 2), ActiveRecord\array_flatten(array(1, array(2))));
		$this->assertEquals(array(1, 2, 3), ActiveRecord\array_flatten(array(1, array(2), 3)));
		$this->assertEquals(array(1, 2, 3, 4), ActiveRecord\array_flatten(array(1, array(2, 3), 4)));
	}

	public function testAll()
	{
		$this->assertTrue(ActiveRecord\all(null,array(null,null)));
		$this->assertTrue(ActiveRecord\all(1,array(1,1)));
		$this->assertFalse(ActiveRecord\all(1,array(1,'1')));
		$this->assertFalse(ActiveRecord\all(null,array('',null)));
	}

};
?>