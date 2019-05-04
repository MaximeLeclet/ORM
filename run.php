<?php

require_once "Post.php";

// INSERT
// $post = new Post();
// $post->content = "1";
// $post->subContent = "1";
// $post->save();

// LOAD
// $post = new Post();
// $post->load(1);

// FIND
// $posts = Post::find();

$post = new Post();
$post->load(10);
$post->content = "Second";
$post->subContent = "Yo";
$post->save();
