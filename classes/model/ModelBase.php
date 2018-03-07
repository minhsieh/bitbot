<?php

namespace model;

use Countable;
use ArrayAccess;
use ArrayIterator;
use JsonSerializable;
use IteratorAggregate;

class ModelBase implements JsonSerializable
{
	protected $data;
	
	public function __construct($data = [])
	{
		$this->data = $data;
	}
	
	public function getData()
	{
		return $this->data;
	}
	
	public function __get($key)
	{
		return isset($this->data[$key])? $this->data[$key]: null;
	}
	
	public function __set($key, $value)
	{
		$this->data[$key] = $value;
	}
	
	public function __isset($key)
	{
		return isset($this->data[$key]);
	}
	
	public function __unset($key)
	{
		unset($this->data[$key]);
	}
	
	public function __invoke()
	{
		return $this->data;
	}
	
	public function jsonSerialize()
	{
		return $this->data;
	}
}