<?php

namespace App\Support\QueueWithStreams;

interface WithStreamsInterface
{
    public function getStream(): ?string;
}