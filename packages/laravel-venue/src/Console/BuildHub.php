<?php

namespace StreetMesh\Venue\Console;

use Illuminate\Console\Command;
use StreetMesh\Venue\Experiences\Experiences;
use StreetMesh\Venue\Hub\Build;

/**
 * Write out this server's hub.
 *
 * A StreetMesh server has at most one hub, and what distinguishes it from any
 * other Colyseus server is the rooms it serves. Only this server knows which
 * those are, so this is where the hub comes from.
 *
 * Run it before starting a hub locally and before deploying one. It is cheap
 * and repeatable: the same installed experiences produce the same artifact,
 * down to the fingerprint.
 */
class BuildHub extends Command
{
    protected $signature = 'hub:build {--into= : Where to write it, defaulting to hub-build/}';

    protected $description = "Write out this server's hub, with the rooms it has installed";

    public function handle(Experiences $experiences): int
    {
        $into = (string) ($this->option('into') ?: config('streetmesh.venue.build.into') ?: base_path('hub-build'));
        $from = (string) (config('streetmesh.venue.build.hub') ?: base_path('hub'));

        $built = (new Build($experiences, $from, $into))->run();

        $this->newLine();
        $this->line('  <fg=gray>from</> '.$from);
        $this->line('  <fg=gray>into</> '.$into);
        $this->newLine();

        if ($built['rooms'] === []) {
            // Not a failure. A venue with nothing installed is a door with
            // nothing behind it, which is what a fresh server looks like.
            $this->line('  <fg=yellow>no rooms</> — nothing installed here has anything live');
        }

        foreach ($built['rooms'] as $room) {
            $this->line("  <fg=green>room</> {$room}");
        }

        $this->newLine();
        $this->line('  <fg=gray>build</> '.$built['fingerprint']);
        $this->newLine();

        return self::SUCCESS;
    }
}
