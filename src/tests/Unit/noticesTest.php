<?php

namespace Dcplibrary\notices\Tests\Unit;

use Dcplibrary\notices\Tests\TestCase;
use Dcplibrary\notices\notices;

class noticesTest extends TestCase
{
    /** @test */
    public function it_can_be_instantiated(): void
    {
        $instance = new notices();
        
        $this->assertInstanceOf(notices::class, $instance);
    }
    
    /** @test */
    public function it_returns_correct_name(): void
    {
        $instance = new notices();
        
        $this->assertEquals('notices', $instance->name());
    }
}
