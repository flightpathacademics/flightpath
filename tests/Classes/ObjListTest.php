<?php


class ObjListTest extends FlightPathTestCase
{
  /**
   * Verifies that a new ObjList starts empty with its counters initialized.
   */
  public function testNewObjListHasExpectedDefaults()
  {
    $list = new ObjList();

    $this->assertSame([], $list->array_list);
    $this->assertSame(0, $list->i);
    $this->assertSame(0, $list->count);
    $this->assertTrue($list->is_empty);
    $this->assertSame(0, $list->get_size());
  }

  /**
   * Verifies that add() appends objects and updates the list's empty and
   * count state.
   */
  public function testAddAppendsObject()
  {
    $list = new ObjList();
    $object = new stdClass();

    $list->add($object);

    $this->assertFalse($list->is_empty);
    $this->assertSame(1, $list->count);
    $this->assertSame(1, $list->get_size());
    $this->assertSame($object, $list->get_first());
  }

  /**
   * Verifies that add() can insert an object at the beginning of the list.
   */
  public function testAddCanInsertAtTop()
  {
    $list = new ObjList();
    $first = new stdClass();
    $second = new stdClass();

    $list->add($first);
    $list->add($second, true);

    $this->assertSame($second, $list->get_first());
    $this->assertSame($first, $list->get_element(1));
    $this->assertSame(2, $list->get_size());
  }

  /**
   * Verifies that index_of() uses an object's equals() method to determine
   * whether two objects represent the same value.
   */
  public function testIndexOfUsesEquals()
  {
    $first = new Course();
    $first->course_id = 988445;

    $second = new Course();
    $second->course_id = 988445;

    $list = new ObjList();
    $list->add($first);

    $this->assertSame(0, $list->index_of($second));
  }

  /**
   * Verifies that object_index_of() distinguishes object identity from
   * equals()-based matching.
   */
  public function testObjectIndexOfUsesObjectIdentity()
  {
    $first = new Course();
    $first->course_id = 988445;

    $second = new Course();
    $second->course_id = 988445;

    $list = new ObjList();
    $list->add($first);

    $this->assertSame(0, $list->object_index_of($first));
    $this->assertSame(-1, $list->object_index_of($second));
  }

  /**
   * Verifies that find_match() returns the matching object rather than its
   * array index, and returns FALSE when no match exists.
   */
  public function testFindMatchReturnsObjectOrFalse()
  {
    $first = new Course();
    $first->course_id = 988445;

    $second = new Course();
    $second->course_id = 988445;

    $other = new Course();
    $other->course_id = 123456;

    $list = new ObjList();
    $list->add($first);

    $this->assertSame($first, $list->find_match($second));
    $this->assertFalse($list->find_match($other));
  }

  /**
   * Verifies that the iterator starts at the beginning after
   * reset_counter() and advances with get_next().
   */
  public function testIteratorBehavior()
  {
    $first = new stdClass();
    $second = new stdClass();

    $list = new ObjList();
    $list->add($first);
    $list->add($second);

    $list->reset_counter();

    $this->assertTrue($list->has_more());
    $this->assertSame($first, $list->get_next());

    $this->assertTrue($list->has_more());
    $this->assertSame($second, $list->get_next());

    $this->assertFalse($list->has_more());
  }

  /**
   * Verifies that find_all_matches() returns every object matching the
   * supplied object and returns FALSE when there are no matches.
   */
  public function testFindAllMatches()
  {
    $first = new Course();
    $first->course_id = 988445;

    $second = new Course();
    $second->course_id = 988445;

    $other = new Course();
    $other->course_id = 123456;

    $list = new ObjList();
    $list->add($first);
    $list->add($second);
    $list->add($other);

    $matches = $list->find_all_matches($first);

    $this->assertInstanceOf(ObjList::class, $matches);
    $this->assertSame(2, $matches->get_size());
    $this->assertSame($first, $matches->get_element(0));
    $this->assertSame($second, $matches->get_element(1));

    $missing = new Course();
    $missing->course_id = 999999;

    $this->assertFalse($list->find_all_matches($missing));
  }

  /**
   * Verifies that insert_after_index() inserts the new object at the
   * specified index while preserving the existing objects.
   */
  public function testInsertAfterIndex()
  {
    $first = new stdClass();
    $second = new stdClass();
    $inserted = new stdClass();

    $list = new ObjList();
    $list->add($first);
    $list->add($second);

    $list->insert_after_index(1, $inserted);

    $this->assertSame(3, $list->get_size());
    $this->assertSame($first, $list->get_element(0));
    $this->assertSame($inserted, $list->get_element(1));
    $this->assertSame($second, $list->get_element(2));
  }

  /**
   * Verifies that refresh_indexes() rebuilds the underlying array and
   * resets the iterator state and count.
   */
  public function testRefreshIndexesResetsIteratorState()
  {
    $first = new stdClass();
    $second = new stdClass();

    $list = new ObjList();
    $list->add($first);
    $list->add($second);

    $list->reset_counter();
    $list->get_next();

    $list->refresh_indexes();

    $this->assertSame(0, $list->i);
    $this->assertSame(2, $list->count);
    $this->assertTrue($list->has_more());
    $this->assertSame($first, $list->get_next());
  }
}