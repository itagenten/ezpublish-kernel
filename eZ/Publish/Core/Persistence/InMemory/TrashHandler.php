<?php
/**
 * File containing the TrashHandler implementation
 *
 * @copyright Copyright (C) 1999-2012 eZ Systems AS. All rights reserved.
 * @license http://www.gnu.org/licenses/gpl-2.0.txt GNU General Public License v2
 * @version //autogentag//
 */

namespace eZ\Publish\Core\Persistence\InMemory;
use eZ\Publish\SPI\Persistence\Content\Location\Trash\Handler as TrashHandlerInterface,
    eZ\Publish\SPI\Persistence\Content\Location\CreateStruct,
    eZ\Publish\API\Repository\Values\Content\Query\Criterion;

/**
 * @see eZ\Publish\SPI\Persistence\Content\Location\Trash\Handler
 */
class TrashHandler implements TrashHandlerInterface
{
    /**
     * @var Handler
     */
    protected $handler;

    /**
     * @var Backend
     */
    protected $backend;

    /**
     * Setups current handler instance with reference to Handler object that created it.
     *
     * @param Handler $handler
     * @param Backend $backend The storage engine backend
     */
    public function __construct( Handler $handler, Backend $backend )
    {
        $this->handler = $handler;
        $this->backend = $backend;
    }

    /**
     * @see eZ\Publish\SPI\Persistence\Content\Location\Trash\Handler
     */
    public function loadTrashItem( $id )
    {
        return $this->backend->load( 'Content\\Location\\Trashed', $id );
    }

    /**
     * @see eZ\Publish\SPI\Persistence\Content\Location\Trash\Handler
     * @todo Handle field types actions
     */
    public function trashSubtree( $locationId )
    {
        $location = $this->handler->locationHandler()->load( $locationId );
        $subtreeLocations = $this->backend->find(
            'Content\\Location',
            array( 'pathString' => $location->pathString . '%' )
        );
        $isLocationRemoved = false;
        $parentLocationId = null;

        foreach ( $subtreeLocations as $location )
        {
            if ( $location->id == $locationId )
            {
                $parentLocationId = $location->parentId;
            }

            if ( $this->backend->find( 'Content\\Location', array( 'contentId' => $location->contentId ) ) == 1 )
            {
                $this->backend->delete( 'Content\\Location', $locationId );
                $this->backend->create( 'Content\\Location\\Trashed', (array)$location, false );
            }
            else
            {
                if ( $location->id == $locationId )
                {
                    $isLocationRemoved = true;
                }
                $this->backend->delete( 'Content\\Location', $location->id );
                $remainingLocations = $this->backend->find( 'Content\\Location', array( 'contentId' => $location->contentId ) );
                $this->backend->updateByMatch(
                    'Content\\Location',
                    array( 'contentId' => $location->contentId ),
                    array( 'mainLocationId' => $remainingLocations[0]->id )
                );
            }
        }

        if ( isset( $parentLocationId ) )
        {
            $parentLocation = $this->handler->locationHandler()->load( $parentLocationId );
            $this->updateSubtreeModificationTime( $parentLocation->pathString );
        }

        return $isLocationRemoved ? null : $this->loadTrashItem( $locationId );
    }

    /**
     * @see eZ\Publish\SPI\Persistence\Content\Location\Trash\Handler
     * @todo Handle field types actions
     */
    public function recover( $trashedId, $newParentId )
    {
        $trashedLocation = $this->loadTrashItem( $trashedId );
        $newParent = $this->handler->locationHandler()->load( $newParentId );

        // Restore location under $newParent
        $struct = new CreateStruct;
        foreach ( $struct as $property => $value )
        {
            if ( isset( $trashedLocation->$property ) )
            {
                $struct->$property = $trashedLocation->$property;
            }
        }

        $struct->parentId = $newParent->id;
        return $this->handler->locationHandler()->create( $struct )->id;
    }

    /**
     * Limited implementation (no criterion/sort support).
     * Will return all trashed locations, regardless criterion filter or sort clauses provided.
     * Offset/Limit is however supported
     *
     * @see eZ\Publish\SPI\Persistence\Content\Location\Trash\Handler
     */
    public function findTrashItems( Criterion $criterion = null, $offset = 0, $limit = null, array $sort = null )
    {
        return array_slice(
            $this->backend->find( 'Content\\Location\\Trashed' ),
            $offset,
            $limit
        );
    }

    /**
     * @see eZ\Publish\SPI\Persistence\Content\Location\Trash\Handler
     */
    public function emptyTrash()
    {
        $trashedIds = array();
        $contentIds = array();
        foreach ( $this->backend->find( 'Content\\Location\\Trashed' ) as $trashed )
        {
            $trashedIds[] = $trashed->id;
            $contentIds[] = $trashed->contentId;
        }

        if ( !empty( $trashedIds ) )
        {
            // Remove associated content for trashed locations
            foreach ( $contentIds as $contentId )
            {
                $this->handler->contentHandler()->deleteContent( $contentId );
            }

            // Remove trashed locations
            $this->backend->deleteByMatch( 'Content\\Location\\Trashed', array( 'id' => $trashedIds ) );
        }
    }

    /**
     * @see eZ\Publish\SPI\Persistence\Content\Location\Trash\Handler
     */
    public function deleteTrashItem( $trashedId )
    {
        $vo = $this->loadTrashItem( $trashedId );
        $this->handler->contentHandler()->deleteContent( $vo->contentId );
        $this->backend->delete( 'Content\\Location\\Trashed', $trashedId );
    }

    /**
     * Updates subtree modification time for all locations starting from $startPathString
     * @param string $pathString
     */
    private function updateSubtreeModificationTime( $pathString )
    {
        $locationIdList = array_filter( explode( '/', $pathString ) );
        $this->backend->updateByMatch(
            'Content\\Location',
            array( 'id' => $locationIdList ),
            array( 'modifiedSubLocation' => time() )
        );
    }
}
