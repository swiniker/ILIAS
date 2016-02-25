<?php

/* Copyright (c) 2016 Stefan Hecken, Extended GPL, see docs/LICENSE */

namespace CaT\Filter;

class Navigator {

	protected $tree;

	public function __construct($tree) {
		$this->tree = $tree;
	}

	public function tree() {
		return $this->tree;
	}

	public function left() {
		$path = $this->path;

		$left_path = (int)$path[count($path) - 1] - 1;

		if($left_path < 0) {
			throw new \OutOfBoundsException("No left neighbor");
		}

		$path[count($path) - 1] = $left_path;
		$left = $this->getItemByPath($path, $this->tree);

		$this->path = $path;

		return $this;
	}

	public function right() {
		$path = $this->path;

		$right_path = (int)$path[count($path) - 1] + 1;
		$path[count($path) - 1] = $right_path;

		$right = $this->getItemByPath($path, $this->tree);

		if($right === null) {
			throw new \OutOfBoundsException("No right neighbor");
		}

		$this->path = $path;

		return $this;
	}

	public function enter() {
		$current = $this->current();

		if(!($current instanceof Filters\FilterList)) {
			throw new \OutOfBoundsException("Not possible to enter node");
		}

		$this->path[] = "0";

		return $this;
	}

	public function up() {
		$path = $this->path;

		if(count($path) == 1) {
			throw new \OutOfBoundsException("Not possible to enter upper node");
		}
		unset($path[count($path)-1]);

		$this->path = $path;

		return $this;
	}

	public function go_to($path) {
		$path = explode(":",$path);
		$tmp = $this->getItemByPath($path, $this->tree);

		if($tmp === null) {
			throw new \OutOfBoundsException("Not possible to select node ".$path);
		}

		$this->path = $path;

		return $this;
	}

	public function current() {
		return $this->getItemByPath($this->path, $this->tree);
	}

	protected function getItemByPath($path, $tmp) {
		foreach ($path as $value) {
			$tmp = $tmp->subs()[$value];
		}

		return $tmp;
	}

	public function path() {
		return implode(":",$this->path);
	}
}