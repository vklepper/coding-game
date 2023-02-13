<?php

fscanf(STDIN, "%d", $lap);
$game = new Game($lap);
fscanf(STDIN, "%d", $checkpointCount);
for ($i = 0; $i < $checkpointCount; $i++) {
    fscanf(STDIN, "%d %d", $cpX, $cpY);
    $cp = new CheckPoint($cpX, $cpY, $i);
    $game->checkpoints[] = $cp;
}

while (TRUE) {
    $game->refreshState();
//    $game->logState();

    foreach ($game->ships as $ship) {
        if ($ship->type === Game::TEAM_OPPONENT) {
            continue;
        }
        if ($ship->move instanceof MoveTurnBack || $ship->move instanceof MoveSharpeTurn) {
            $pDistToObj = Tools::getDistance($ship->previousPosition, $ship->cp);
            $distToObj = Tools::getDistance($ship, $ship->cp);
            if ($distToObj > $pDistToObj) {
                $ship->move = null;
            } else {
                dump(__LINE__);
                response($ship->move->target, 0);
                continue;
            }
        }

        // Orientation si pas dans l'axe
        if ($ship->getAngleToCp(true) > 70) {
            dump(__LINE__);

            response($ship->cp, 0);
            continue;
        }

        // Orientation si pas dans l'axe
        if ($ship->getAngleToCp(true) > 55) {
            dump(__LINE__);

            response($ship->cp, 60);
            continue;
        }

        // Si dernier checkpoint, pas de question a se poser GOGOGOGO
        if ($ship->lap === 3 && $ship->cp->position == count($game->checkpoints)) {
            if ($game->boostRemaining > 0 && $ship->getAngleToCp(true) < 10) {
                dump('boost de fin');

                response($ship->cp, -1);
            } else {
                dump(__LINE__);

                response($ship->cp, 100);
            }
            continue;
        }

        if (makeMove($ship)) {
            dump(__LINE__);

            continue;
        }

        if (useBoost($ship, $game)) {
            dump(__LINE__);

            continue;
        }

        if ($ship->cp->getDistanceFrom($ship) < 3000) {
            dump(__LINE__);

            response($ship->cp, 100);
            continue;
        }

        dump(__LINE__);

        response($ship->cp, 100);

    }

}

function useBoost(Ship $ship, Game $game): bool
{
    // Pas de boost dans le premier tour
    if ($game->boostRemaining === 0 || $ship->lap === 1) {
        return false;
    }
    if ($ship->getAngleToCp() < 15 && $ship->getAngleToCp() > -15) {
        /*
        if ($game->lap === 3) {
            // Si ligne droite, on envois
            if ($game->cp->getDistanceFrom($game->ship) > 7500 && false) {
                $game->boostRemaining--;
                response($game->cp, -1);
                return true;
            }
            $angle = $game->getNextAngle(true);
            // Si nos prochaines checkpoints sont alignés sur un boost restant
            // au 3ᵉ tour, c'est qu'on n'a pas de grosse ligne droite donc go for it
            if ($angle > 150) {
                $game->boostRemaining--;
                response($game->cp, -1);
                return true;
            }
        }
        */

        // Si ligne droite, on envoie un boost
        if ($ship->cp->getDistanceFrom($ship) > 10000) {
            $game->boostRemaining--;
            response($ship->cp, -1);
            return true;
        }
    }

    return false;
}

function makeMove(Ship $ship): bool
{
    $angle = $ship->getNextAngle(true);
    if ($angle) {
        if (MoveTurnBack::ready($ship)) {
            $move = new MoveTurnBack($ship->getNextCheckPoint());
            $ship->move = $move;
            response($move->target, 0);
            return true;
        }

        if (MoveSharpeTurn::ready($ship)) {
            $move = new MoveSharpeTurn($ship->getNextCheckPoint());
            $ship->move = $move;
            response($move->target, 0);
            return true;
        }
    }

    return false;
}

class Coordinates
{
    /** @var int */
    public $x;

    /** @var int */
    public $y;

    public function __construct(int $x, int $y)
    {
        $this->x = $x;
        $this->y = $y;
    }

    public function getDistanceFrom(Coordinates $p): int
    {
        return Tools::getDistance($this, $p);
    }
}


class Game
{
    const TEAM_ROCKET = 1;
    const TEAM_OPPONENT = 2;

    /** @var int */
    public $lap;

    /** @var CheckPoint[] */
    public $checkpoints = [];

    /** @var Ship[] */
    public $ships = [];

    /** @var int */
    public $boostRemaining = 1;

    public function __construct(int $lap)
    {
        $this->lap = $lap;
    }

    /**
     * @throws Exception
     */
    public function initShip(int $id, int $type, int $x, int $y, int $cpId, int $angle = null)
    {
        $cp = $this->checkpoints[$cpId];
        if (!isset($this->ships[$id])) {
            $ship = new Ship($this, $x, $y, $type, $cp, $angle);
            $this->ships[$id] = $ship;
            $ship->cp = $cp;
        } else {
            $ship = $this->ships[$id];
            $ship->updatePosition($x, $y);
            $this->updateCheckpoint($ship, $cp);
//            $ship->_setIsSafe();
        }
    }

    /**
     * @throws Exception
     */
    public function updateCheckpoint(Ship $ship, CheckPoint $cp)
    {
        // Si le checkpoint a changé, c'est qu'on a franchi le précédent
        if ($ship->cp !== $cp) {
            $ship->move = null;
            $ship->pCp = $ship->cp;
            $ship->cp = $cp;
            if ($ship->cp->position === 1) {
                $ship->lap++;
            }

            if ($ship->lap > 1) {
                $ship->_setNextCheckPoint();
            }
        }
        $ship->_calculateAngleToCp();
        $ship->_calculateNextAngle();
    }

    /**
     * @throws Exception
     */
    public function refreshState(): bool
    {
        fscanf(STDIN, "%d %d %d %d %d %d", $x, $y, $vx, $vy, $angle, $cpId);
        $this->initShip(1, Game::TEAM_ROCKET, $x, $y, $cpId, $angle);
        fscanf(STDIN, "%d %d %d %d %d %d", $x, $y, $vx, $vy, $angle, $cpId);
        $this->initShip(2, Game::TEAM_ROCKET, $x, $y, $cpId, $angle);

        fscanf(STDIN, "%d %d %d %d %d %d", $x, $y, $vx, $vy, $angle, $cpId);
        $this->initShip(3, Game::TEAM_OPPONENT, $x, $y, $cpId, $angle);
        fscanf(STDIN, "%d %d %d %d %d %d", $x, $y, $vx, $vy, $angle, $cpId);
        $this->initShip(4, Game::TEAM_OPPONENT, $x, $y, $cpId, $angle);

        return true;
    }

    public function logState()
    {
        dump([
            'lap' => $this->lap,
            'boostRemaining' => $this->boostRemaining,
            'ships' => $this->ships
        ]);
    }

    public function logFullState()
    {
        dump($this);
    }
}

abstract class Move
{
    const TYPE_TURNBACK = 1;
    const TYPE_SHARPETURN = 2;

    /** @var int */
    public $type;
}

class MoveSharpeTurn extends Move
{
    const DIST = 1500;
    const DIST_IF_SAFE = 2000;
    const MIN_SPEED = 450;
    const MIN_DIST = 1200;

    /**  @var Coordinates */
    public $target;

    public function __construct(Coordinates $target)
    {
        dump("MoveSharpeTurn");
        $this->type = Move::TYPE_SHARPETURN;
        $this->target = $target;
    }

    public static function ready(Ship $ship): bool
    {
        if ($ship->getNextAngle(true) > 40 && $ship->getNextAngle(true) < 140) {
            return false;
        }
        if ($ship->cp->getDistanceFrom($ship) < self::MIN_DIST) {
            return false;
        }
        if ($ship->getSpeed() < self::MIN_SPEED) {
            return false;
        }
        if ($ship->cp->getDistanceFrom($ship) > self::DIST_IF_SAFE) {
            return false;
        }
        if ($ship->cp->getDistanceFrom($ship) <= self::DIST_IF_SAFE && $ship->isSafe()) {
            return true;
        }
        if ($ship->cp->getDistanceFrom($ship) < self::DIST) {
            return true;
        }
        return true;
    }
}

class MoveTurnBack extends Move
{
    const DIST = 1800;
    const DIST_IF_SAFE = 2500;
    const MIN_SPEED = 500;
    const MIN_DIST = 800;

    /**  @var Coordinates */
    public $target;

    public function __construct(Coordinates $target)
    {
        dump("MoveTurnBack");
        $this->type = Move::TYPE_TURNBACK;
        $this->target = $target;
    }

    public static function ready(Ship $ship): bool
    {
        if ($ship->getNextAngle(true) > 10 && $ship->getNextAngle(true) < 170) {
            return false;
        }
        if ($ship->cp->getDistanceFrom($ship) < self::MIN_DIST) {
            return false;
        }
        if ($ship->getSpeed() < self::MIN_SPEED) {
            return false;
        }
        if ($ship->cp->getDistanceFrom($ship) > self::DIST_IF_SAFE) {
            return false;
        }
        if ($ship->cp->getDistanceFrom($ship) <= self::DIST_IF_SAFE && $ship->isSafe()) {
            return true;
        }
        if ($ship->cp->getDistanceFrom($ship) < self::DIST) {
            return true;
        }
        return false;
    }
}

class CheckPoint extends Coordinates
{
    /** @var int */
    public $position;

    public function __construct(int $x, int $y, int $position)
    {
        parent::__construct($x, $y);
        $this->position = $position;
    }
}

class Ship extends Coordinates
{
    /** @var int */
    public $type;

    /** @var int */
    public $lap = 1;
    /**
     * @var int
     */
    public $angleToCp;

    /** @var Game */
    protected $game;

    /** @var ?CheckPoint */
    public $cp = null;

    /** @var ?CheckPoint */
    public $nCp = null;

    /** @var ?CheckPoint */
    public $pCp = null;

    /** @var ?Move */
    public $move = null;

    /** @var ?float */
    public $nextAngle = null;

    /** @var Coordinates */
    public $previousPosition = null;

    /** @var Bool */
    public $isSafe = false;

    /** @var ?int */
    public $speed = null;

    /** @var ?int */
    public $previousSpeed = null;

    /** @var int */
    public $angle;

    public function __construct(Game $game, int $x, int $y, int $type, CheckPoint $cp, int $angle)
    {
        parent::__construct($x, $y);
        $this->game = $game;
        $this->x = $x;
        $this->y = $y;
        $this->type = $type;
        $this->cp = $cp;
        $this->angle = $angle;
    }

    public function updatePosition(int $x, int $y)
    {
        if ($this->getSpeed()) {
            $this->previousSpeed = $this->getSpeed();
        }
        $this->previousPosition = new Coordinates($this->x, $this->y);
        $this->x = $x;
        $this->y = $y;

        $this->speed = Tools::getDistance($this->previousPosition, $this);
    }

    public function getSpeed(): ?int
    {
        return $this->speed;
    }

    public function isSafe(): bool
    {
        return $this->isSafe;
    }

    public function _setIsSafe()
    {
        $this->isSafe = true;
        // todo réfléchir à ça
//        if ($this->opponent !== null && $this->ship !== null) {
//            $dist = Tools::getDistance($this->opponent, $this->ship);
//            $this->isSafe = $dist > 2000;
//        }
    }

    public function getAngleToCp(bool $absolute = false): int
    {
        if ($absolute) {
            return abs($this->angleToCp);
        }

        return $this->angleToCp;
    }

    public function getNextAngle(bool $absolute = false): ?int
    {
        if ($absolute) {
            return abs($this->nextAngle);
        }
        return $this->nextAngle;
    }

    public function getNextCheckPoint(): ?CheckPoint
    {
        return $this->nCp;
    }

    /**
     * @throws Exception
     */
    public function _calculateAngleToCp(): void
    {
        $this->angleToCp = Tools::getGlobalAngle($this, $this->cp);
    }

    public function _calculateNextAngle(): void
    {
        if ($this->cp === null || $this->nCp === null) {
            return;
        }
        $angle = Tools::getAngle($this, $this->cp, $this->nCp);
        $this->nextAngle = $angle;
    }

    public function _setNextCheckPoint(): void
    {
        if ($this->cp->position === count($this->game->checkpoints)) {
            $this->nCp = $this->game->checkpoints[1];
        }

        $this->nCp = $this->game->checkpoints[$this->cp->position];
    }
}

class Tools
{
    public static function getAngle(Coordinates $p1, Coordinates $p2, Coordinates $p3): int
    {
        $vector1 = [
            "x" => $p2->x - $p1->x,
            "y" => $p2->y - $p1->y
        ];
        $vector2 = [
            "x" => $p3->x - $p2->x,
            "y" => $p3->y - $p2->y
        ];
        $angle = rad2deg(atan2($vector2['y'] - $vector1['y'], $vector2['x'] - $vector1['x']));

        return (int)round($angle);
    }

    /**
     * @throws Exception
     */
    public static function getGlobalAngle(Coordinates $p1, Coordinates $p2): int
    {
//        $d = self::getDistance($p1, $p2);
        $q = self::findQuadrant($p1, $p2);

        $diff = new Coordinates($p1->x - $p2->x, $p1->y - $p2->y);
        $localAngle = atan2($diff->y, $diff->x);
        $globalAngle = 0;
        if ($q === 1 || $q === 2) {
            $globalAngle = pi() - $localAngle;
        } elseif ($q === 3 || $q === 4) {
            $globalAngle = -pi() - $localAngle;
        }

        return round($globalAngle);
    }

    public static function getDistance(Coordinates $p1, Coordinates $p2): int
    {
        $x = $p2->x - $p1->x;
        $y = $p2->y - $p1->y;
        $d = hypot($x, $y);

        return (int)round($d);
    }

    /**
     * @throws Exception
     */
    public static function findQuadrant(Coordinates $origin, Coordinates $target): int
    {
        if ($target->x > $origin->x and $target->y < $origin->y) {
            return 1;
        } elseif ($target->x < $origin->x and $target->y < $origin->y) {
            return 2;
        } elseif ($target->x < $origin->x and $target->y > $origin->y) {
            return 3;
        } elseif ($target->x > $origin->x and $target->y > $origin->y) {
            return 4;
        }
        throw new Exception('Error in findQuadrant()');
    }
}

function response(Coordinates $point, int $power)
{
    if ($power === -1) {
        echo sprintf('%d %d %s', $point->x, $point->y, "BOOST") . "\n";
    } else {
        echo sprintf('%d %d %d', $point->x, $point->y, $power) . "\n";
    }
}

function dump($var)
{
    error_log(var_export($var, true));
}
