<?php

namespace StreetMesh\Chess;

use StreetMesh\Protocol\Scope;
use StreetMesh\Venue\Experiences\Audience;
use StreetMesh\Venue\Experiences\Experience;
use StreetMesh\Venue\Experiences\Settles;
use StreetMesh\Venue\Gatherings\Gathering;

/**
 * Chess, as something a venue hosts.
 *
 * Written as a capability first, which was wrong and said so: two of the four
 * methods it had to implement returned empty strings, because chess has no
 * service type to announce and no front page to greet anybody with. Chess is
 * not a kind of server. It is a thing you can do at one.
 *
 * The visible symptom was that it appeared in the main navigation beside
 * "Residents" and "Experiences", as though a server could be a chess in the way
 * it can be a domicile.
 */
final class ChessExperience implements Experience, Settles
{
    /**
     * One name for three things: the collection its records go in, the room
     * type its hub serves, and the experience itself.
     */
    public const COLLECTION = 'com.streetmesh.games.chess';

    /**
     * A finished game, written down.
     *
     * The venue calls this when the hub says a game is over — including when
     * that happens with nobody left watching, which is the case a browser can
     * never report and the commonest way a game actually ends.
     *
     * Doing it twice writes nothing twice: Games::settle leaves a concluded
     * gathering alone, and a browser knocking and the hub announcing are two
     * messengers with the same news.
     *
     * @param  array<string, mixed>  $result
     */
    public function settle(Gathering $gathering, array $result): void
    {
        app(Games::class)->settle($gathering, [
            'outcome' => (string) ($result['outcome'] ?? ''),
            'winner' => (string) ($result['winner'] ?? ''),
            'moves' => array_values((array) ($result['moves'] ?? [])),
            'positions' => array_values((array) ($result['positions'] ?? [])),
            'fen' => (string) ($result['fen'] ?? ''),
        ]);
    }

    public function name(): string
    {
        return self::COLLECTION;
    }

    public function title(): string
    {
        return 'Chess';
    }

    public function description(): string
    {
        return 'Play somebody who lives on another server, and keep your own record of it.';
    }

    public function icon(): string
    {
        return 'squares-2x2';
    }

    public function route(): string
    {
        return 'chess.lobby';
    }

    /**
     * Nobody launches a game of chess.
     *
     * Narrowed from the interface's nullable, because this always has an
     * answer — null there means "call it whatever a venue calls things".
     */
    public function action(): string
    {
        return 'Play';
    }

    /**
     * The games, for somebody who has not come to play.
     *
     * The same list as `route()` shows, at an address that asks nothing of
     * anybody. Playing still means arriving first — the buttons that act are
     * behind the door wherever the list is read from — but looking at what is
     * on does not, and it used to.
     *
     * @return array{label: string, route: string}
     */
    public function watching(): array
    {
        return ['label' => 'Watch', 'route' => 'chess.watch'];
    }

    /**
     * Chess is a spectator sport.
     *
     * Anybody holding the address may watch, including somebody who has never
     * been to this venue. Nothing is given away that both players cannot
     * already see, and a game people can watch is a better thing than a game
     * they cannot.
     *
     * The same answer for every game so far, which is the simplest thing this
     * can say and not the only thing it will ever say. The gathering is a
     * parameter because the day a game wants to be unlisted, here is where it
     * says so.
     */
    public function audience(Gathering $gathering): Audience
    {
        return Audience::Anybody;
    }

    /**
     * Adding, and never altering.
     *
     * A venue that could change a game after the fact could change who won, so
     * it asks for the least that works — and a visitor reading this on their own
     * server's consent screen can see the difference.
     *
     * @return array<int, string>
     */
    public function scopes(): array
    {
        return [(string) Scope::forRepo([self::COLLECTION], [Scope::CREATE])];
    }

    /**
     * The referee, which is not written in PHP and does not run here.
     *
     * Chess needs somebody to hold the board and refuse an illegal move, so
     * this package ships a room next to the screens. The venue copies it into
     * the hub it builds.
     */
    public function room(): string
    {
        return realpath(__DIR__.'/../room') ?: __DIR__.'/../room';
    }
}
