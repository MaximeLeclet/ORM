<?php

interface EntityInterface
{
  public function save();
  public function load($id);
  public static function find($clauseWhere);
}
