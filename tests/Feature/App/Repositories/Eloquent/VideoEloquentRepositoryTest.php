<?php

namespace Tests\Feature\App\Repositories\Eloquent;

use App\Enums\ImageTypes;
use App\Models\CastMember;
use App\Models\Category;
use App\Models\Genre;
use App\Models\Video as Model;
use App\Repositories\Eloquent\VideoEloquentRepository;
use Core\Domain\Entity\Video as EntityVideo;
use Core\Domain\Enum\MediaStatus;
use Core\Domain\Enum\Rating;
use Core\Domain\Exception\NotFoundException;
use Core\Domain\Repository\VideoRepositoryInterface;
use Core\Domain\ValueObject\Image as ValueObjectImage;
use Core\Domain\ValueObject\Media as ValueObjectMedia;
use Core\Domain\ValueObject\Uuid;
use DateTime;
use Tests\TestCase;

class VideoEloquentRepositoryTest extends TestCase
{
    protected $repository;

    protected function setUp(): void
    {
        parent::setUp();

        $this->repository = new VideoEloquentRepository(
            new Model
        );
    }

    public function test_implements_interface()
    {
        $this->assertInstanceOf(
            VideoRepositoryInterface::class,
            $this->repository
        );
    }

    public function test_insert()
    {
        $entity = new EntityVideo(
            title: 'Test',
            description: 'Test',
            yearLaunched: 2026,
            rating: Rating::L,
            duration: 1,
            opened: true,
        );

        $this->repository->insert($entity);

        $this->assertDatabaseHas('videos', [
            'id' => $entity->id(),
        ]);
    }

    public function test_insert_with_relationships()
    {
        $categories = Category::factory()->count(4)->create();
        $genres = Genre::factory()->count(4)->create();
        $castMembers = CastMember::factory()->count(4)->create();

        $entity = new EntityVideo(
            title: 'Test',
            description: 'Test',
            yearLaunched: 2026,
            rating: Rating::L,
            duration: 1,
            opened: true,
        );
        foreach ($categories as $category) {
            $entity->addCategoryId($category->id);
        }
        foreach ($genres as $genre) {
            $entity->addGenre($genre->id);
        }
        foreach ($castMembers as $castMember) {
            $entity->addCastMember($castMember->id);
        }

        $entityInDb = $this->repository->insert($entity);

        $this->assertDatabaseHas('videos', [
            'id' => $entity->id(),
        ]);

        $this->assertDatabaseCount('category_video', 4);
        $this->assertDatabaseCount('genre_video', 4);
        $this->assertDatabaseCount('cast_member_video', 4);

        $this->assertEquals($categories->pluck('id')->toArray(), $entityInDb->categoriesId);
        $this->assertEquals($genres->pluck('id')->toArray(), $entityInDb->genresId);
        $this->assertEquals($castMembers->pluck('id')->toArray(), $entityInDb->castMemberIds);
    }

    public function test_not_found_video()
    {
        $this->expectException(NotFoundException::class);

        $this->repository->findById('fake_value');
    }

    public function test_fin_by_id()
    {
        $video = Model::factory()->create();

        $response = $this->repository->findById($video->id);

        $this->assertEquals($video->id, $response->id());
        $this->assertEquals($video->title, $response->title);
    }

    public function test_find_all()
    {
        Model::factory()->count(10)->create();

        $response = $this->repository->findAll();

        $this->assertCount(10, $response);
    }

    public function test_find_all_with_filter()
    {
        Model::factory()->count(10)->create();
        Model::factory()->count(10)->create([
            'title' => 'Test',
        ]);

        $response = $this->repository->findAll(
            filter: 'Test'
        );

        $this->assertCount(10, $response);
        $this->assertDatabaseCount('videos', 20);
    }

    /**
     * @dataProvider dataProviderPagination
     */
    public function test_pagination(
        int $page,
        int $totalPage,
        int $total = 50,
    ) {
        Model::factory()->count($total)->create();

        $response = $this->repository->paginate(
            page: $page,
            totalPage: $totalPage
        );

        $this->assertCount($totalPage, $response->items());
        $this->assertEquals($total, $response->total());
        $this->assertEquals($page, $response->currentPage());
        $this->assertEquals($totalPage, $response->perPage());
    }

    public function dataProviderPagination(): array
    {
        return [
            [
                'page' => 1,
                'totalPage' => 10,
                'total' => 100,
            ], [
                'page' => 2,
                'totalPage' => 15,
            ], [
                'page' => 3,
                'totalPage' => 15,
            ],
        ];
    }

    public function test_update_not_found_id()
    {
        $this->expectException(NotFoundException::class);

        $entity = new EntityVideo(
            title: 'Test',
            description: 'Test',
            yearLaunched: 2026,
            rating: Rating::L,
            duration: 1,
            opened: true,
        );

        $this->repository->update($entity);
    }

    public function test_update()
    {
        $categories = Category::factory()->count(10)->create();
        $genres = Genre::factory()->count(10)->create();
        $castMembers = CastMember::factory()->count(10)->create();

        $videoDb = Model::factory()->create();

        $this->assertDatabaseHas('videos', [
            'title' => $videoDb->title,
        ]);

        $entity = new EntityVideo(
            id: new Uuid($videoDb->id),
            title: 'Test',
            description: 'Test',
            yearLaunched: 2026,
            rating: Rating::L,
            duration: 1,
            opened: true,
            createdAt: new DateTime($videoDb->created_at),
        );

        foreach ($categories as $category) {
            $entity->addCategoryId($category->id);
        }
        foreach ($genres as $genre) {
            $entity->addGenre($genre->id);
        }
        foreach ($castMembers as $castMember) {
            $entity->addCastMember($castMember->id);
        }

        $entityInDb = $this->repository->update($entity);

        $this->assertDatabaseHas('videos', [
            'title' => 'Test',
        ]);

        $this->assertDatabaseCount('category_video', 10);
        $this->assertDatabaseCount('genre_video', 10);
        $this->assertDatabaseCount('cast_member_video', 10);

        $this->assertEquals($categories->pluck('id')->toArray(), $entityInDb->categoriesId);
        $this->assertEquals($genres->pluck('id')->toArray(), $entityInDb->genresId);
        $this->assertEquals($castMembers->pluck('id')->toArray(), $entityInDb->castMemberIds);
    }

    public function test_delete_not_found()
    {
        $this->expectException(NotFoundException::class);

        $this->repository->delete('fake_value');
    }

    public function test_delete()
    {
        $video = Model::factory()->create();

        $this->assertDatabaseHas('videos', [
            'id' => $video->id,
        ]);

        $this->repository->delete($video->id);

        $this->assertSoftDeleted('videos', [
            'id' => $video->id,
        ]);
    }

    public function test_insert_with_media_trailer()
    {
        $entity = new EntityVideo(
            title: 'Test',
            description: 'Test',
            yearLaunched: 2026,
            rating: Rating::L,
            duration: 1,
            opened: true,
            trailerFile: new ValueObjectMedia(
                filePath: 'test.mp4',
                mediaStatus: MediaStatus::PROCESSING,
            ),
        );
        $this->repository->insert($entity);

        $this->assertDatabaseCount('medias_video', 0);
        $this->repository->updateMedia($entity);
        $this->assertDatabaseHas('medias_video', [
            'video_id' => $entity->id(),
            'file_path' => 'test.mp4',
            'media_status' => MediaStatus::PROCESSING->value,
        ]);

        $entity->setTrailerFile(new ValueObjectMedia(
            filePath: 'test2.mp4',
            mediaStatus: MediaStatus::COMPLETE,
            encodedPath: 'test2.xpto',
        ));

        $entityDb = $this->repository->updateMedia($entity);
        $this->assertDatabaseCount('medias_video', 1);
        $this->assertDatabaseHas('medias_video', [
            'video_id' => $entity->id(),
            'file_path' => 'test2.mp4',
            'media_status' => MediaStatus::COMPLETE->value,
            'encoded_path' => 'test2.xpto',
        ]);

        $this->assertNotNull($entityDb->trailerFile());
    }

    public function test_insert_with_media_video()
    {
        $entity = new EntityVideo(
            title: 'Test',
            description: 'Test',
            yearLaunched: 2026,
            rating: Rating::L,
            duration: 1,
            opened: true,
            videoFile: new ValueObjectMedia(
                filePath: 'test.mp4',
                mediaStatus: MediaStatus::PROCESSING,
            ),
        );
        $this->repository->insert($entity);

        $this->assertDatabaseCount('medias_video', 0);
        $this->repository->updateMedia($entity);
        $this->assertDatabaseHas('medias_video', [
            'video_id' => $entity->id(),
            'file_path' => 'test.mp4',
            'media_status' => MediaStatus::PROCESSING->value,
        ]);

        $entity->setVideoFile(new ValueObjectMedia(
            filePath: 'test2.mp4',
            mediaStatus: MediaStatus::COMPLETE,
            encodedPath: 'test2.xpto',
        ));

        $entityDb = $this->repository->updateMedia($entity);
        $this->assertDatabaseCount('medias_video', 1);
        $this->assertDatabaseHas('medias_video', [
            'video_id' => $entity->id(),
            'file_path' => 'test2.mp4',
            'media_status' => MediaStatus::COMPLETE->value,
            'encoded_path' => 'test2.xpto',
        ]);

        $this->assertNotNull($entityDb->videoFile());
    }

    public function test_insert_with_image_banner()
    {
        $entity = new EntityVideo(
            title: 'Test',
            description: 'Test',
            yearLaunched: 2026,
            rating: Rating::L,
            duration: 1,
            opened: true,
            bannerFile: new ValueObjectImage(
                path: 'test.jpg',
            ),
        );
        $this->repository->insert($entity);
        $this->assertDatabaseCount('images_video', 0);

        $this->repository->updateMedia($entity);
        $this->assertDatabaseHas('images_video', [
            'video_id' => $entity->id(),
            'path' => 'test.jpg',
            'type' => ImageTypes::BANNER->value,
        ]);

        $entity->setBannerFile(new ValueObjectImage(
            path: 'test2.jpg',
        ));
        $entityDb = $this->repository->updateMedia($entity);
        $this->assertDatabaseHas('images_video', [
            'video_id' => $entity->id(),
            'path' => 'test2.jpg',
            'type' => ImageTypes::BANNER->value,
        ]);
        $this->assertDatabaseCount('images_video', 1);

        $this->assertNotNull($entityDb->bannerFile());
    }

    public function test_insert_with_image_thumb()
    {
        $entity = new EntityVideo(
            title: 'Test',
            description: 'Test',
            yearLaunched: 2026,
            rating: Rating::L,
            duration: 1,
            opened: true,
            thumbFile: new ValueObjectImage(
                path: 'test.jpg',
            ),
        );
        $this->repository->insert($entity);
        $this->assertDatabaseCount('images_video', 0);

        $this->repository->updateMedia($entity);
        $this->assertDatabaseHas('images_video', [
            'video_id' => $entity->id(),
            'path' => 'test.jpg',
            'type' => ImageTypes::THUMB->value,
        ]);

        $entity->setThumbFile(new ValueObjectImage(
            path: 'test2.jpg',
        ));
        $entityDb = $this->repository->updateMedia($entity);
        $this->assertDatabaseHas('images_video', [
            'video_id' => $entity->id(),
            'path' => 'test2.jpg',
            'type' => ImageTypes::THUMB->value,
        ]);
        $this->assertDatabaseCount('images_video', 1);

        $this->assertNotNull($entityDb->thumbFile());
    }

    public function test_insert_with_image_thumb_half()
    {
        $entity = new EntityVideo(
            title: 'Test',
            description: 'Test',
            yearLaunched: 2026,
            rating: Rating::L,
            duration: 1,
            opened: true,
            thumbHalf: new ValueObjectImage(
                path: 'test.jpg',
            ),
        );
        $this->repository->insert($entity);
        $this->assertDatabaseCount('images_video', 0);

        $this->repository->updateMedia($entity);
        $this->assertDatabaseHas('images_video', [
            'video_id' => $entity->id(),
            'path' => 'test.jpg',
            'type' => ImageTypes::THUMB_HALF->value,
        ]);

        $entity->setThumbHalf(new ValueObjectImage(
            path: 'test2.jpg',
        ));
        $entityDb = $this->repository->updateMedia($entity);
        $this->assertDatabaseHas('images_video', [
            'video_id' => $entity->id(),
            'path' => 'test2.jpg',
            'type' => ImageTypes::THUMB_HALF->value,
        ]);
        $this->assertDatabaseCount('images_video', 1);

        $this->assertNotNull($entityDb->thumbHalf());
    }
}
