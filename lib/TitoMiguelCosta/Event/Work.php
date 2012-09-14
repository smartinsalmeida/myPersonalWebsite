<?php

namespace TitoMiguelCosta\Event;

use Silex\Application;
use Symfony\Component\EventDispatcher\Event;

class Work extends Event
{
    const SUBMIT = 'work.submit';
    protected $app;
    protected $data;

    public function __construct(Application $app, array $data)
    {
        $this->app = $app;
        $this->data = $data;
    }

    public function getApplication()
    {
        return $this->app;
    }

    public function getData()
    {
        return $this->data;
    }

}
