<?
include 'helpers/config.php';

class SerializationTest extends DatabaseTest
{
	public function test_to_json()
	{
		$book = Book::find(1);
		$json = $book->to_json();
		$this->assertEquals($book->attributes(),(array)json_decode($json));
	}

	public function test_to_json_only()
	{
		$book = Book::find(1);
		$json = $book->to_json(array('only' => array('name', 'special')));
		$this->assertEquals(array('name','special'),array_keys((array)json_decode($json)));
	}

	public function test_to_json_only_not_array()
	{
		$book = Book::find(1);
		$json = $book->to_json(array('only' => 'name'));
		$this->assertEquals(array('name'),array_keys((array)json_decode($json)));
	}

	public function test_to_json_except()
	{
		$book = Book::find(1);
		$json = $book->to_json(array('except' => array('name','special')));
		$decoded = array_flip(array_keys((array)json_decode($json)));
		$this->assertFalse(array_key_exists('name',$decoded));
		$this->assertFalse(array_key_exists('special',$decoded));
	}

	public function test_to_json_except_not_array()
	{
		$book = Book::find(1);
		$json = $book->to_json(array('except' => 'name'));
		$decoded = array_flip(array_keys((array)json_decode($json)));
		$this->assertFalse(array_key_exists('name',$decoded));
	}

	public function test_to_json_methods()
	{
		$book = Book::find(1);
		$json = $book->to_json(array('methods' => array('upper_name')));
		$decoded = (array)json_decode($json);
		$this->assertTrue(array_key_exists('upper_name',array_flip(array_keys($decoded))));
		$this->assertTrue(ActiveRecord\Inflector::is_upper($decoded['upper_name']));
	}

	public function test_to_json_methods_not_array()
	{
		$book = Book::find(1);
		$json = $book->to_json(array('methods' => 'upper_name'));
		$decoded = (array)json_decode($json);
		$this->assertTrue(array_key_exists('upper_name',array_flip(array_keys($decoded))));
		$this->assertTrue(ActiveRecord\Inflector::is_upper($decoded['upper_name']));
	}

	//methods added last should we shuld have value of the method in our json
	//rather than the regular attribute value
	public function test_to_json_methods_method_same_as_attribute()
	{
		$book = Book::find(1);
		$json = $book->to_json(array('methods' => 'name'));
		$decoded = (array)json_decode($json);
		$this->assertTrue(array_key_exists('name',array_flip(array_keys($decoded))));
		$this->assertTrue(ActiveRecord\Inflector::is_lower($decoded['name']));
	}

	public function test_to_json_include()
	{
		$book = Book::find(1);
		$json = $book->to_json(array('include' => array('author')));
		$decoded = (array)json_decode($json);
		$this->assertTrue(array_key_exists('parent_author_id', $decoded['author']));
	}

/*	public function test_to_json_include_nested_with_nested_options()
	{
		$venue = Venue::find(1);
		$json = $venue->to_json(array('include' => array('events' => array('except' => 'title', 'include' => array('host' => array('only' => 'id'))))));
		$decoded = (array)json_decode($json);
		$event = $decoded['events'][0];
		$host = $event->host;

		$this->assertEquals(1, $decoded['id']);
		$this->assertNotNull($host->id);
		$this->assertNull(@$event->host->name);
		$this->assertNull(@$event->title);
	}*/

	public function test_to_xml()
	{
		$book = Book::find(1);
		$this->assertEquals($book->attributes(),get_object_vars(new SimpleXMLElement($book->to_xml())));
	}
};
?>