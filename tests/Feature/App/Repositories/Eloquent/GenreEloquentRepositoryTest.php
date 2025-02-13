<?php

namespace Tests\Feature\App\Repositories\Eloquent;

use App\Models\Category;
use App\Models\Genre as Model;
use App\Repositories\Eloquent\GenreEloquentRepository;
use Core\Domain\Entity\Genre as EntityGenre;
use Core\Domain\Exception\NotFoundException;
use Core\Domain\Repository\GenreRepositoryInterface;
use Core\Domain\ValueObject\Uuid;
use DateTime;
use Ramsey\Uuid\Uuid as RamseyUuid;
use Tests\TestCase;

class GenreEloquentRepositoryTest extends TestCase
{
    protected $repository;

    protected function setUp(): void
    {
        parent::setUp();

        $this->repository = new GenreEloquentRepository(new Model);
    }

    public function test_implements_interface()
    {
        $this->assertInstanceOf(GenreRepositoryInterface::class, $this->repository);
    }

    public function test_insert()
    {
        $entity = new EntityGenre(name: 'New genre');

        $response = $this->repository->insert($entity);
        $this->assertEquals($entity->name, $response->name);
        $this->assertEquals($entity->id, $response->id);

        $this->assertDatabaseHas('genres', [
            'id' => $entity->id(),
        ]);
    }

    public function test_insert_deactivate()
    {
        $entity = new EntityGenre(name: 'New genre');
        $entity->deactivate();

        $this->repository->insert($entity);

        $this->assertDatabaseHas('genres', [
            'id' => $entity->id(),
            'is_active' => false,
        ]);
    }

    public function test_insert_with_relationships()
    {
        $categories = Category::factory()->count(4)->create();

        $genre = new EntityGenre(name: 'teste');
        foreach ($categories as $category) {
            $genre->addCategory($category->id);
        }

        $response = $this->repository->insert($genre);

        $this->assertDatabaseHas('genres', [
            'id' => $response->id(),
        ]);

        $this->assertDatabaseCount('category_genre', 4);
    }

    public function test_not_found_by_id()
    {
        $this->expectException(NotFoundException::class);

        $genre = 'fake_value';

        $this->repository->findById($genre);
    }

    public function test_find_by_id()
    {
        $genre = Model::factory()->create();

        $response = $this->repository->findById($genre->id);

        $this->assertEquals($genre->id, $response->id());
        $this->assertEquals($genre->name, $response->name);
    }

    public function test_find_all()
    {
        $genres = Model::factory()->count(10)->create();

        $genresDb = $this->repository->findAll();

        $this->assertEquals(count($genres), count($genresDb));
    }

    public function test_find_all_empty()
    {
        $genresDb = $this->repository->findAll();

        $this->assertCount(0, $genresDb);
    }

    public function test_find_all_with_filter()
    {
        Model::factory()->count(10)->create([
            'name' => 'Teste',
        ]);
        Model::factory()->count(10)->create();

        $genresDb = $this->repository->findAll(
            filter: 'Teste'
        );
        $this->assertEquals(10, count($genresDb));

        $genresDb = $this->repository->findAll();
        $this->assertEquals(20, count($genresDb));
    }

    public function test_pagination()
    {
        Model::factory()->count(60)->create();

        $response = $this->repository->paginate();

        $this->assertEquals(15, count($response->items()));
        $this->assertEquals(60, $response->total());
    }

    public function test_pagination_empty()
    {
        $response = $this->repository->paginate();

        $this->assertCount(0, $response->items());
        $this->assertEquals(0, $response->total());
    }

    public function test_update()
    {
        $genre = Model::factory()->create();

        $entity = new EntityGenre(
            id: new Uuid($genre->id),
            name: $genre->name,
            isActive: (bool) $genre->is_active,
            createdAt: new DateTime($genre->created_at)
        );

        $entity->update(
            name: 'Name Updated'
        );

        $response = $this->repository->update($entity);

        $this->assertEquals('Name Updated', $response->name);

        $this->assertDatabaseHas('genres', [
            'name' => 'Name Updated',
        ]);
    }

    public function test_update_not_found()
    {
        $this->expectException(NotFoundException::class);

        $genreId = (string) RamseyUuid::uuid4();

        $entity = new EntityGenre(
            id: new Uuid($genreId),
            name: 'name',
            isActive: true,
            createdAt: new DateTime(date('Y-m-d H:i:s'))
        );

        $entity->update(
            name: 'Name Updated'
        );

        $this->repository->update($entity);
    }

    public function test_delete_not_found()
    {
        $this->expectException(NotFoundException::class);

        $this->repository->delete('fake_id');
    }

    public function test_delete()
    {
        $genre = Model::factory()->create();

        $response = $this->repository->delete($genre->id);

        $this->assertSoftDeleted('genres', [
            'id' => $genre->id,
        ]);
        $this->assertTrue($response);
    }
}
