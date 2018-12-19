<?php

namespace Makeable\LaravelFactory\Tests\Feature;

use App\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Makeable\LaravelFactory\FactoryBuilder;
use Makeable\LaravelFactory\Tests\Stubs\Company;
use Makeable\LaravelFactory\Tests\Stubs\Division;
use Makeable\LaravelFactory\Tests\TestCase;

class RelationsTest extends TestCase
{
    use RefreshDatabase;

    /** @test **/
    function it_creates_models_with_belongs_to_relations()
    {
        $company = $this->factory(Company::class)
            ->with('owner')
            ->create();

        $this->assertInstanceOf(Company::class, $company);
        $this->assertEquals(1, $company->owner->id);
    }

    /** @test **/
    function it_creates_models_with_has_many_relations()
    {
        $company = $this->factory(Company::class)
            ->with(2, 'divisions')
            ->create();

        $this->assertInstanceOf(Company::class, $company);
        $this->assertInstanceOf(Division::class, $company->divisions->first());
        $this->assertEquals(2, $company->divisions->count());
    }

    /** @test **/
    function it_creates_models_with_belongs_to_many_relations()
    {
        $division = $this->factory(Division::class)
            ->with(2, 'employees')
            ->create();

        $this->assertInstanceOf(User::class, $division->employees->first());
        $this->assertEquals(2, $division->employees->count());
    }

    /** @test **/
    function it_accepts_pivot_attributes_on_belongs_to_many_relations()
    {
        $division = $this->factory(Division::class)->with(1, 'employees', function ($employee) {
            $employee->fillPivot(['started_at' => '2019-01-01 00:00:00']);
        })->create();

        $employees = $division->employees()->withPivot('started_at')->get();

        $this->assertEquals('2019-01-01 00:00:00', $employees->first()->pivot->started_at);
        $this->assertEquals(1, $employees->count());
    }

    /** @test **/
    function it_accepts_closures_as_pivot_attributes_and_they_will_evaluate_on_each_model()
    {
        [$i, $dates] = [0, [now()->subMonth(), now()->subDay()]];

        $division = $this->factory(Division::class)->with(2, 'employees', function ($employee) use ($dates, &$i) {
            $employee->fillPivot(function ($faker) use ($dates, &$i) {
                return ['started_at' => $dates[$i++]];
            });
        })->create();

        $employees = $division->employees()->withPivot('started_at')->get();

        $this->assertEquals(2, $employees->count());
        $this->assertEquals($dates[0]->toDateTimeString(), $employees->get(0)->pivot->started_at);
        $this->assertEquals($dates[1]->toDateTimeString(), $employees->get(1)->pivot->started_at);
    }

    /** @test **/
    function it_creates_models_with_multiple_relations()
    {
        $company = $this->factory(Company::class)
            ->with('owner')
            ->with(2, 'divisions')
            ->create();

        $this->assertInstanceOf(User::class, $company->owner);
        $this->assertInstanceOf(Division::class, $company->divisions->first());
    }

    /** @test **/
    function it_creates_models_with_nested_relations()
    {
        $company = $this->factory(Company::class)
            ->with('owner')
            ->with('divisions.manager')
            ->create();

        $this->assertEquals(1, $company->divisions->count());
        $this->assertInstanceOf(User::class, $company->divisions->first()->manager);
    }

    /** @test **/
    function nested_relations_can_be_composed_by_array_syntax()
    {
        $company = $this->factory(Company::class)
            ->with('owner')
            ->with([
                'divisions' => 2,
                'divisions.manager',
            ])
            ->create();

        $this->assertEquals(2, $company->divisions->count());
        $this->assertInstanceOf(User::class, $company->divisions->first()->manager);
        $this->assertNotEquals(
            $company->divisions->get(0)->manager->id,
            $company->divisions->get(1)->manager->id
        );
    }

    /** @test **/
    function nested_relations_can_be_customized_by_closures()
    {
        $company = $this->factory(Company::class)
            ->with('owner')
            ->with([
                'divisions' => function (FactoryBuilder $divisions) {
                    $divisions
                        ->fill(['name' => 'foo'])
                        ->times(2)
                        ->with('manager');
                },
            ])
            ->create();

        $this->assertEquals(2, $company->divisions->count());
        $this->assertEquals('foo', $company->divisions->first()->name);
        $this->assertInstanceOf(User::class, $company->divisions->first()->manager);
        $this->assertNotEquals(
            $company->divisions->get(0)->manager->id,
            $company->divisions->get(1)->manager->id
        );
    }

    /** @test **/
    function nested_relations_can_be_specified_separate_function_calls()
    {
        $company = $this->factory(Company::class)
            ->with('owner')
            ->with(1, 'divisions')
            ->with(1, 'divisions.manager')
            ->create();

        $this->assertEquals(1, $company->divisions->count());
        $this->assertInstanceOf(User::class, $company->divisions->first()->manager);
    }

    /** @test **/
    function the_same_relation_can_be_created_multiple_times_using_andWith()
    {
        $company = $this->factory(Company::class)
            ->with('owner')
            ->with(1, 'divisions')
            ->andWith(1, 'divisions.manager')
            ->create();

        $this->assertEquals(2, $company->divisions->count());
        $this->assertNull($company->divisions->get(0)->manager);
        $this->assertInstanceOf(User::class, $company->divisions->get(1)->manager);
    }

    /** @test **/
    function it_will_associate_a_belongs_to_model_instance_instead_of_creating_through_factory()
    {
        $user = $this->factory(User::class)->create();
        $company = $this->factory(Company::class)
            ->with('owner', $user)
            ->create();

        $this->assertEquals(1, User::count());
        $this->assertEquals($user->id, $company->owner->id);
    }

    /** @test **/
    function it_will_associate_only_the_first_item_in_a_belongs_to_collection()
    {
        $users = $this->factory(User::class)->times(2)->create();
        $company = $this->factory(Company::class)
            ->with('owner', $users)
            ->create();

        $this->assertEquals(2, User::count());
        $this->assertEquals($users->first()->id, $company->owner->id);
    }

    /** @test **/
    function it_accepts_a_collection_of_instances_to_use_for_belongs_to_many_relations()
    {
        $users = $this->factory(User::class)->times(2)->create();
        $division = $this->factory(Division::class)
            ->with('employees', $users)
            ->create();

        $this->assertEquals([1,2], $division->employees->pluck('id')->toArray());
    }

    /** @test **/
    function it_accepts_a_collection_of_instances_and_tops_up_with_factory_to_specified_amount()
    {
        // Top up with 1 factory employee
        $division1 = $this->factory(Division::class)
            ->with(3, 'employees', $this->factory(User::class)->times(2)->create())
            ->create();

        $this->assertEquals([1,2,3], $division1->employees->pluck('id')->toArray());

        // Slice given users to 2 employees
        $division2 = $this->factory(Division::class)
            ->with(2, 'employees', $this->factory(User::class)->times(3)->create())
            ->create();

        $this->assertEquals([4,5], $division2->employees->pluck('id')->toArray());
    }

    /** @test **/
    public function states_can_be_specified_for_nested_relations()
    {
        $company = $this->factory(Company::class)
            ->with('owner')
            ->with(2, 'happy', 'customers')
            ->with(3, 'active', 'divisions')
            ->with(3, 'divisions.employees')
            ->create();

        $this->assertEquals(3, $company->divisions->count());
        $this->assertEquals(2, $company->customers->count());
        $this->assertEquals(3, $company->divisions->first()->employees->count());
        $this->assertEquals(1, $company->divisions->first()->active);
        $this->assertEquals(5, $company->customers->first()->satisfaction);
    }
}
