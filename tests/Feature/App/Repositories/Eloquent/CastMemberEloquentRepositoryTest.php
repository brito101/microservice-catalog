<?php

namespace Tests\Feature\App\Repositories\Eloquent;

use App\Models\CastMember as Model;
use App\Repositories\Eloquent\CastMemberEloquentRepository;
use Core\Domain\Entity\CastMember as Entity;
use Core\Domain\Enum\CastMemberType;
use Core\Domain\Exception\NotFoundException;
use Core\Domain\Repository\CastMemberRepositoryInterface;
use Core\Domain\ValueObject\Uuid as ValueObjectUuid;
use Tests\TestCase;

class CastMemberEloquentRepositoryTest extends TestCase
{
    protected $repository;

    protected function setUp(): void
    {
        parent::setUp();

        $this->repository = new CastMemberEloquentRepository(new Model);
    }

    public function test_chech_implements_interface_repository()
    {
        $this->assertInstanceOf(CastMemberRepositoryInterface::class, $this->repository);
    }

    public function test_insert()
    {
        $entity = new Entity(
            name: 'teste',
            type: CastMemberType::ACTOR,
        );

        $response = $this->repository->insert($entity);

        $this->assertDatabaseHas('cast_members', [
            'id' => $entity->id(),
        ]);
        $this->assertEquals($entity->name, $response->name);
    }

    public function test_find_by_id_not_found()
    {
        $this->expectException(NotFoundException::class);

        $this->repository->findById('fake_id');
    }

    public function test_find_by_id()
    {
        $castMember = Model::factory()->create();

        $response = $this->repository->findById($castMember->id);

        $this->assertEquals($castMember->id, $response->id());
        $this->assertEquals($castMember->name, $response->name);
    }

    public function test_find_all_emtpy()
    {
        $response = $this->repository->findAll();
        $this->assertCount(0, $response);
    }

    public function test_find_all()
    {
        $castMembers = Model::factory()->count(50)->create();

        $response = $this->repository->findAll();

        $this->assertCount(count($castMembers), $response);
    }

    public function test_pagination()
    {
        Model::factory()->count(20)->create();

        $response = $this->repository->paginate();

        $this->assertCount(15, $response->items());
        $this->assertEquals(20, $response->total());
    }

    public function test_pagination_with_total_page()
    {
        Model::factory()->count(80)->create();

        $response = $this->repository->paginate(
            totalPage: 10
        );

        $this->assertCount(10, $response->items());
        $this->assertEquals(80, $response->total());
    }

    public function test_update_not_found()
    {
        $this->expectException(NotFoundException::class);

        $entity = new Entity(
            name: 'teste',
            type: CastMemberType::DIRECTOR
        );

        $this->repository->update($entity);
    }

    public function test_update()
    {
        $castMember = Model::factory()->create();

        $entity = new Entity(
            id: new ValueObjectUuid($castMember->id),
            name: 'new name',
            type: CastMemberType::DIRECTOR
        );

        $response = $this->repository->update($entity);

        $this->assertNotEquals($castMember->name, $response->name);
        $this->assertEquals('new name', $response->name);
    }

    public function test_delete_not_found()
    {
        $this->expectException(NotFoundException::class);

        $this->repository->delete('fake_id');
    }

    public function test_delete()
    {
        $castMember = Model::factory()->create();

        $this->repository->delete($castMember->id);

        $this->assertSoftDeleted('cast_members', [
            'id' => $castMember->id,
        ]);
    }
}
