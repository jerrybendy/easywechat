<?php

/*
 * This file is part of the overtrue/wechat.
 *
 * (c) overtrue <i@overtrue.me>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace EasyWeChat\Tests\OfficialAccount\Material;

use EasyWeChat\Applications\Base\Core\Http;
use EasyWeChat\Applications\OfficialAccount\Material\Client as Material;
use EasyWeChat\Applications\OfficialAccount\Message\Article;
use EasyWeChat\Tests\TestCase;
use GuzzleHttp\Psr7\Response;

class ClientTest extends TestCase
{
    /**
     * Return mock http.
     *
     * @return \Mockery\MockInterface
     */
    public function getMaterial()
    {
        $accessToken = \Mockery::mock('EasyWeChat\Applications\OfficialAccount\Core\AccessToken');
        $accessToken->shouldReceive('getQueryFields')->andReturn(['access_token' => 'foo']);
        $material = \Mockery::mock('EasyWeChat\Applications\OfficialAccount\Material\Client[parseJSON]', [$accessToken]);
        $material->shouldReceive('parseJSON')->andReturnUsing(function () {
            return func_get_args()[1];
        });

        return $material;
    }

    public function getMockAccessToken()
    {
        $token = \Mockery::mock('EasyWeChat\Applications\OfficialAccount\Core\AccessToken[getQueryFields]', ['foo', 'bar']);
        $token->shouldReceive('getQueryFields')->andReturn(['access_token' => 'foo']);

        return $token;
    }

    /**
     * Test for uploadImage().
     *
     * @expectedException \EasyWeChat\Exceptions\InvalidArgumentException
     */
    public function testUploadImage()
    {
        $material = $this->getMaterial();

        $response = $material->uploadImage(__DIR__.'/stubs/image.jpg');

        $this->assertStringStartsWith(Material::API_UPLOAD, $response[0]);
        $this->assertContains('stubs/image.jpg', $response[1]['media']);

        // exception
        $response = $material->uploadImage(__DIR__.'/stubs/foo.jpg');
    }

    /**
     * Test for uploadVoice().
     */
    public function testUploadVoice()
    {
        $material = $this->getMaterial();

        $response = $material->uploadVoice(__DIR__.'/stubs/voice.wma');

        $this->assertStringStartsWith(Material::API_UPLOAD, $response[0]);
        $this->assertContains('stubs/voice.wma', $response[1]['media']);
    }

    /**
     * Test for uploadThumb().
     */
    public function testUploadThumb()
    {
        $material = $this->getMaterial();

        $response = $material->uploadThumb(__DIR__.'/stubs/thumb.png');

        $this->assertStringStartsWith(Material::API_UPLOAD, $response[0]);
        $this->assertContains('stubs/thumb.png', $response[1]['media']);
    }

    /**
     * Test for uploadVideo().
     */
    public function testUploadVideo()
    {
        $material = $this->getMaterial();

        $response = $material->uploadVideo(__DIR__.'/stubs/video.mp4', 'foo', 'a mp4 video.');

        $this->assertStringStartsWith(Material::API_UPLOAD, $response[0]);
        $this->assertContains('stubs/video.mp4', $response[1]['media']);
        $this->assertSame(json_encode(['title' => 'foo', 'introduction' => 'a mp4 video.']), $response[2]['description']);
    }

    /**
     * Test for uploadArticle().
     */
    public function testUploadArticle()
    {
        $material = $this->getMaterial();

        $response = $material->uploadArticle(['foo' => 'bar']);

        $this->assertStringStartsWith(Material::API_NEWS_UPLOAD, $response[0]);
        $this->assertSame(['articles' => ['foo' => 'bar']], $response[1]);

        $response = $material->uploadArticle(new Article(['title' => 'foo']));

        $this->assertSame(['articles' => [['title' => 'foo']]], $response[1]);

        $response = $material->uploadArticle([new Article(['title' => 'foo', 'show_cover' => 0]), new Article(['title' => 'bar'])]);

        $this->assertSame(['articles' => [['title' => 'foo', 'show_cover_pic' => 0], ['title' => 'bar']]], $response[1]);
    }

    /**
     * Test for updateArticle().
     */
    public function testUpdateArticle()
    {
        $material = $this->getMaterial();

        $response = $material->updateArticle('foo', ['title' => 'bar']);

        $this->assertStringStartsWith(Material::API_NEWS_UPDATE, $response[0]);
        $this->assertSame('foo', $response[1]['media_id']);
        $this->assertSame(0, $response[1]['index']);
        $this->assertSame(['title' => 'bar'], $response[1]['articles']);

        // multi
        $response = $material->updateArticle('foo', [['title' => 'bar']]);
        $this->assertSame(['title' => 'bar'], $response[1]['articles']);

        // invalid $article
        $response = $material->updateArticle('foo', ['abc' => 'bar']);
        $this->assertSame([], $response[1]['articles']);
    }

    /**
     * Test for uploadArticleImage().
     */
    public function testUploadArticleImage()
    {
        $material = $this->getMaterial();

        $response = $material->uploadArticleImage(__DIR__.'/stubs/image.jpg');

        $this->assertStringStartsWith(Material::API_NEWS_IMAGE_UPLOAD, $response[0]);
        $this->assertContains('stubs/image.jpg', $response[1]['media']);
    }

    /**
     * Test for get().
     */
    public function testGet()
    {
        $material = $this->getMaterial();
        $http = \Mockery::mock(Http::class.'[json]');
        $http->shouldReceive('addMiddleware')->andReturn($http);
        $http->shouldReceive('json')->andReturnUsing(function ($api, $params) {
            return new Response(200, ['Content-Type' => ['text/plain']], json_encode(compact('api', 'params')));
        });
        $material->setHttp($http);

        // news
        $response = $material->get('foo');

        $this->assertStringStartsWith(Material::API_GET, $response['api']);
        $this->assertSame(['media_id' => 'foo'], $response['params']);

        // media
        $http = \Mockery::mock(Http::class.'[json]');
        $http->shouldReceive('addMiddleware')->andReturn($http);
        $http->shouldReceive('json')->andReturnUsing(function ($api, $params) {
            return new Response(200, ['Content-Type' => ['media/video']], 'media content');
        });
        $material->setHttp($http);

        $response = $material->get('bar');

        $this->assertSame('media content', $response);
    }

    /**
     * Test for delete().
     */
    public function testDelete()
    {
        $material = $this->getMaterial();

        $response = $material->delete('foo');

        $this->assertStringStartsWith(Material::API_DELETE, $response[0]);
        $this->assertSame(['media_id' => 'foo'], $response[1]);
    }

    /**
     * Test for lists().
     */
    public function testLists()
    {
        $material = $this->getMaterial();

        // normal
        $response = $material->lists('image');

        $params = [
            'type' => 'image',
            'offset' => 0,
            'count' => 20,
        ];

        $this->assertStringStartsWith(Material::API_LISTS, $response[0]);
        $this->assertSame($params, $response[1]);

        // out of range
        $response = $material->lists('image', 1, 21);

        $params = [
            'type' => 'image',
            'offset' => 1,
            'count' => 20, // 21 -> 20
        ];

        $this->assertStringStartsWith(Material::API_LISTS, $response[0]);
        $this->assertSame($params, $response[1]);
    }

    /**
     * Test for stats().
     */
    public function testStats()
    {
        $material = $this->getMaterial();

        $response = $material->stats();

        $this->assertStringStartsWith(Material::API_STATS, $response[0]);
    }
}