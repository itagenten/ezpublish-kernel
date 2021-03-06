<?php
/**
 * File containing the PurgeClientSingleRequestTest class.
 *
 * @copyright Copyright (C) eZ Systems AS. All rights reserved.
 * @license For full copyright and license information view LICENSE file distributed with this source code.
 * @version //autogentag//
 */

namespace eZ\Publish\Core\MVC\Symfony\Cache\Tests\Http;

use eZ\Publish\Core\MVC\Symfony\Cache\Http\PurgeClientSingleRequest;

class PurgeClientSingleRequestTest extends HttpBasedPurgeClientTest
{
    /**
     * @covers eZ\Publish\Core\MVC\Symfony\Cache\Http\PurgeClientSingleRequest::purge
     */
    public function testPurge()
    {
        $purgeServer = 'http://localhost/';
        $this->configResolver
            ->expects( $this->once() )
            ->method( 'getParameter' )
            ->with( 'http_cache.purge_servers' )
            ->will( $this->returnValue( array( $purgeServer ) ) );

        $locations = array( 123, 456, 789 );

        $this->httpBrowser
            ->expects( $this->once() )
            ->method( 'call' )
            ->with( $purgeServer, 'PURGE', array( 'X-Group-Location-Id' => implode( '; ', $locations ) ) );

        $purgeClient = new PurgeClientSingleRequest( $this->configResolver, $this->httpBrowser );
        $purgeClient->purge( $locations );
    }
}
