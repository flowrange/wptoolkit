<?php

namespace Flowrange\WPToolkit;

/**
 * Allows a class to decorate a post
 *
 * @author Florent Geffroy <contact@flowrange.fr>
 */
abstract class PostDecorator
{


    /**
     * The decorated post
     * @var \WP_Post
     */
    protected $post;


    /**
     * Returns the decorated post
     * @return \WP_Post
     */
    public function getPost()
    {
        return $this->post;
    }


    /**
     * Returns the post ID
     * @return int
     */
    public function getId()
    {
        return (int)$this->post->ID;
    }


    /**
     * Returns the post title
     * @return string
     */
    public function getTitle()
    {
        return get_the_title($this->post);
    }


    /**
     * Constructor
     *
     * @param \WP_Post $post     The decorated post
     * @param string   $postType The expected post type
     *
     * @throws \InvalidArgumentException If the post has not the expected type
     */
    public function __construct(\WP_Post $post, $postType)
    {
        if ($post->post_type !== $postType && $post->post_type !== 'revision') {

            throw new \InvalidArgumentException(
                sprintf(
                    'The post should be of type "%s" (%s found)',
                    $postType,
                    $post->post_type));
        }
        $this->post = $post;
    }


    /**
     * Magic method __isset
     *
     * __isset is called on the post
     *
     * @param string $name Property name
     *
     * @return bool
     */
    public function __isset($name)
    {
        return $this->post->__isset($name);
    }


    /**
     * Magic method __get
     *
     * __get is called on the post
     *
     * @param string $name Property name
     *
     * @return mixed
     */
    public function __get($name)
    {
        return $this->post->__get($name);
    }


    /**
     * \WP_Post::filter
     *
     * @param string $filter
     *
     * @return mixed
     */
    public function filter($filter)
    {
        return $this->post->filter($filter);
    }


    /**
     * \WP_Post::to_array
     *
     * @return array
     */
    public function to_array()
    {
        return $this->post->to_array();
    }

}
