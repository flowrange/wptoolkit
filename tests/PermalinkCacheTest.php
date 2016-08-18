<?php

namespace Flowrange\WPToolkit\Tests;

use Flowrange\WPToolkit\PermalinkCache;

/**
 * PermalinkCache tests
 *
 * @author Florent Geffroy <contact@flowrange.fr>
 */
class PermalinkCacheTest extends \PHPUnit_Framework_TestCase
{


    public function setUp()
    {
        \WP_Mock::setUp();
    }


    public function tearDown()
    {
        \WP_Mock::tearDown();
    }


    public function testConstructorShouldAddSavePostHookToClearPostCache()
    {
        \WP_Mock::expectActionAdded(
            'save_post',
            [
                \WP_Mock\Functions::type(PermalinkCache::class),
                'clearPostCache'
            ]);

        $cache = new PermalinkCache('some-group', 1234);

        $this->assertInstanceOf(
            PermalinkCache::class,
            $cache);
    }


    public function testClearPostCacheCallsWpCacheDelete()
    {
        $cache = new PermalinkCache('some-group', 1234);

        \WP_Mock::wpFunction(
            'wp_cache_delete',
            [
                'time' => 1,
                'args' => [123, 'some-group']
            ]);

        $cache->clearPostCache(123);
    }


    public function testGetPermalinkGetsPermalinkAndStoresItInCache()
    {
        $cache = new PermalinkCache('some-group', 1234);

        \WP_Mock::wpFunction(
            'wp_cache_get',
            [
                'time' => 1,
                'args' => [123, 'some-group', false, false]
            ]);

        \WP_Mock::wpFunction(
            'get_permalink',
            [
                'time'   => 1,
                'args'   => [123, false],
                'return' => '/some/url/'
            ]);

        \WP_Mock::wpFunction(
            'wp_cache_set',
            [
                'time' => 1,
                'args' => [123, '/some/url/', 'some-group', 1234]
            ]);

        $permalink = $cache->getPermalink(123);

        $this->assertEquals(
            '/some/url/',
            $permalink);
    }


    public function testGetPermalinkRemovesWhateverProtocolThereIs()
    {
        $cache = new PermalinkCache('some-group', 1234);

        \WP_Mock::wpFunction(
            'wp_cache_get',
            [
                'time' => 1,
                'args' => [123, 'some-group', false, false]
            ]);

        \WP_Mock::wpFunction(
            'get_permalink',
            [
                'time'   => 1,
                'args'   => [123, false],
                'return' => 'some-protocol://www.example.org/my-post/'
            ]);

        \WP_Mock::wpFunction(
            'wp_cache_set',
            [
                'time' => 1,
                'args' => [123, '//www.example.org/my-post/', 'some-group', 1234]
            ]);

        $permalink = $cache->getPermalink(123);

        $this->assertEquals(
            '//www.example.org/my-post/',
            $permalink);
    }


    public function testGetPermalinkRetrievesPermalinkFromCache()
    {
        $cache = new PermalinkCache('some-group', 1234);

        \WP_Mock::wpFunction(
            'wp_cache_get',
            [
                'time'   => 1,
                'args'   => [123, 'some-group', false, false],
                'return' => '//www.example.org/my-post/'
            ]);

        \WP_Mock::wpFunction(
            'get_permalink',
            [
                'time'   => 0
            ]);

        \WP_Mock::wpFunction(
            'wp_cache_set',
            [
                'time' => 0
            ]);

        $permalink = $cache->getPermalink(123);

        $this->assertEquals(
            '//www.example.org/my-post/',
            $permalink);
    }


    public function testGetPermalinkCanBeCalledWithAnInstanceOfWPPost()
    {
        $cache = new PermalinkCache('some-group', 1234);

        $post = $this->getMock('\WP_Post');
        $post->ID = 123;

        \WP_Mock::wpFunction(
            'wp_cache_get',
            [
                'time' => 1,
                'args' => [123, 'some-group', false, false]
            ]);

        \WP_Mock::wpFunction(
            'get_permalink',
            [
                'time'   => 1,
                'args'   => [$post, false],
                'return' => 'some-protocol://www.example.org/my-post/'
            ]);

        \WP_Mock::wpFunction(
            'wp_cache_set',
            [
                'time' => 1,
                'args' => [123, '//www.example.org/my-post/', 'some-group', 1234]
            ]);

        $permalink = $cache->getPermalink($post);

        $this->assertEquals(
            '//www.example.org/my-post/',
            $permalink);
    }


    public function testGetPermalinkReturnsEmptyStringIfInvalidPost()
    {
        $cache = new PermalinkCache('some-group', 1234);

        $permalink = $cache->getPermalink(new \stdclass());

        $this->assertEquals(
            '',
            $permalink);
    }

}
