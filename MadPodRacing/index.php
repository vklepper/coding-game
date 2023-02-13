<?php

$game = new Game();

while (TRUE) {
    //error_log(var_export("array cp", true));
    //error_log(var_export($checkPoints, true));
    $game->refreshState();
    $game->logState();

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
                response($ship->move->target, 0);
                continue;
            }
        }

        // Orientation si pas dans l'axe
        if ($ship->cp->getAngle(true) > 70) {
            response($ship->cp, 0);
            continue;
        }

        // Orientation si pas dans l'axe
        if ($ship->cp->getAngle(true) > 55) {
            response($ship->cp, 60);
            continue;
        }
        /*
            // Orientation si pas dans l'axe
            if ($ship->cp->getAngle(true) > 30) {
                response($ship->cp, 40);
                continue;
            }
        */
        // Si dernier checkpoint, pas de question a se poser GOGOGOGO
        if ($ship->lap === 3 && $ship->cp->position == count($ship->checkpoints)) {
            if ($ship->boostRemaining > 0 && $ship->cp->getAngle(true) < 10) {
                response($ship->cp, -1);
            } else {
                response($ship->cp, 100);
            }
            continue;
        }

        if (makeMove($ship)) {
            continue;
        }

        if (useBoost($ship)) {
            continue;
        }

        if ($ship->cp->getDistanceFrom($ship) < 3000) {
            response($ship->cp, 100);
            continue;
        }

        response($ship->cp, 100);
    }

}

function useBoost(Ship $ship): bool
{
    // Pas de boost dans le premier tour
    if ($ship->boostRemaining === 0 || $ship->lap === 1) {
        return false;
    }
    if ($ship->cp->angle < 15 && $ship->cp->angle > -15) {
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
            $ship->boostRemaining--;
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

class Game
{
    const TEAM_ROCKET = 1;
    const TEAM_OPPONENT = 2;
    /** @var Ship[] */
    public $ships = [];

    /**
     * @throws Exception
     */
    public function initShip(int $id, int $type, $x, $y, $cpX = null, $cpY = null, $cpAngle = null)
    {
        $ship = $this->ships[$id];
        if ($ship === null) {
            $ship = new Ship($x, $y, $type);
            if ($type === Game::TEAM_OPPONENT) {
                return;
            }
            $ship->cp = new CheckPoint($cpX, $cpY, 1);
            $ship->checkpoints[$ship->cp->position] = $ship->cp;
        } else {
            $ship->updatePosition($x, $y);
            if ($type === Game::TEAM_OPPONENT) {
                return;
            }
            $this->updateCheckpoint($ship, $cpX, $cpY, $cpAngle);

            $ship->_setIsSafe();
        }
    }

    /**
     * @throws Exception
     */
    public function updateCheckpoint(Ship $ship, $cpX, $cpY, $cpAngle)
    {
        // Si le checkpoint a changé, c'est qu'on a franchi le précédent
        if ($ship->cp->x !== $cpX || $ship->cp->y !== $cpY) {
            $ship->move = null;
            $res = array_values(array_filter($ship->checkpoints, function (CheckPoint $checkPoint) use ($cpX, $cpY) {
                return $checkPoint->x === $cpX && $checkPoint->y === $cpY;
            }));

            if (count($res) === 1) {
                $ship->pCp = $ship->cp;
                $ship->cp = $res[0];
                if ($ship->cp->position === 1) {
                    $ship->lap++;
                }
            } elseif (count($res) === 0) {
                $ship->cp = new CheckPoint($cpX, $cpY, count($ship->checkpoints) + 1);
                $ship->checkpoints[$ship->cp->position] = $ship->cp;
            } else {
                throw new Exception("fuck");
            }

            if ($ship->lap > 1) {
                $ship->nCp = $ship->_setNextCheckPoint();
            }
        }

        $ship->nextAngle = $ship->_calculateNextAngle();
        $ship->cp->setAngle($cpAngle);
    }

    /**
     * @throws Exception
     */
    public function refreshState(): bool
    {
        fscanf(STDIN, "%d %d %d %d %d %d", $x, $y, $cpX, $cpY, $cpDist, $cpAngle);
        $this->initShip(1, Game::TEAM_ROCKET, $x, $y, $cpX, $cpY, $cpAngle);
        fscanf(STDIN, "%d %d %d %d %d %d", $x, $y, $cpX, $cpY, $cpDist, $cpAngle);
        $this->initShip(2, Game::TEAM_ROCKET, $x, $y, $cpX, $cpY, $cpAngle);

        fscanf(STDIN, "%d %d", $opponentX, $opponentY);
        $this->initShip(3, Game::TEAM_OPPONENT, $opponentX, $opponentY);
        fscanf(STDIN, "%d %d", $opponentX, $opponentY);
        $this->initShip(4, Game::TEAM_OPPONENT, $opponentX, $opponentY);

        return true;
    }

    public function logState()
    {
//        dump([
//            'lap' => $this->lap,
//            'boostRemaining' => $this->boostRemaining,
//            'direction' => $this->cp->position,
//            'distance' => Tools::getDistance($this->ship, $this->cp),
//            'isSafe' => $this->isSafe(),
//            'angleToTarget' => $this->cp->getAngle(),
//            'angle' => $this->getNextAngle(),
//            'speed' => $this->ship->getSpeed(),
//            'move' => $this->move ? get_class($this->move) : null,
//        ]);
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

    /**  @var Coordinate */
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

    /**  @var Coordinate */
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

class CheckPoint extends Coordinates
{
    /** @var int */
    public $position;

    /**@var int */
    public $angle;

    public function __construct(int $x, int $y, int $position)
    {
        parent::__construct($x, $y);
        $this->x = $x;
        $this->y = $y;
        $this->position = $position;
    }

    public function setAngle(int $angle): self
    {
        $this->angle = $angle;

        return $this;
    }

    public function getAngle(bool $abs = false): int
    {
        return $abs ? abs($this->angle) : $this->angle;
    }
}


class Ship extends Coordinates
{
    /** @var int */
    public $type;
    /** @var int */
    public $lap = 1;

    /** @var int */
    public $boostRemaining = 1;

    public $checkpoints = [];

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

    /** @var Bool */
    public $isSafe = false;

    /** @var ?int */
    public $speed = null;

    /** @var ?int */
    public $previousSpeed = null;

    /** ?Coordinates */
    public $previousPosition = null;

    public function __construct(int $x, int $y, int $type)
    {
        parent::__construct($x, $y);
        $this->x = $x;
        $this->y = $y;
        $this->type = $type;
    }

    public function updatePosition(int $x, int $y)
    {
        if ($this->getSpeed()) {
            $this->previousSpeed = $this->getSpeed();
        }
        $this->previousPosition = new Coordinates($this->x, $this->y);
        $this->x = $x;
        $this->y = $y;

        $this->speed = Tools::getDistance($this->previousPosition, new Coordinates($x, $y));
    }

    public function getSpeed(): ?int
    {
        return $this->speed;
    }

    public function getPreviousSpeed(): ?int
    {
        return $this->previousSpeed;
    }

    public function isAccelerating(): ?bool
    {
        if ($this->getSpeed() && $this->getPreviousSpeed()) {
            return $this->getPreviousSpeed() < $this->getSpeed();
        }

        return null;
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

    public function getNextAngle(bool $absolute = false): ?float
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

    public function _calculateNextAngle(): ?float
    {
        if ($this->cp === null || $this->nCp === null) {
            return null;
        }
        return Tools::getAngle($this, $this->cp, $this->nCp);
    }

    public function _setNextCheckPoint(): ?CheckPoint
    {
        // Si c'est le premier tour, on ne connait pas encore tous les checkpoints
        if ($this->lap === 1) {
            return null;
        }

        if ($this->cp->position === count($this->checkpoints)) {
            return $this->checkpoints[1];
        }

        return $this->checkpoints[$this->cp->position + 1];
    }
}

class Vector
{
    public $x;
    public $y;
    public $angle;
    public $abs;

    public function __construct(int $x, int $y)
    {
        $this->x = $x;
        $this->y = $y;
        $this->angle = atan2($x, $y);
        $this->abs = hypot($x, $y);
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

    public static function getDistance(Coordinates $p1, Coordinates $p2): int
    {
        $x = $p2->x - $p1->x;
        $y = $p2->y - $p1->y;
        $d = hypot($x, $y);

        return (int)round($d);
    }

    /**
     * @throws exception
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
        throw new exception('fuck');
    }

    /**
     * @throws exception
     */
    public static function getVector(Coordinates $p1, Coordinates $p2): Vector
    {
        $quadrant = Tools::findQuadrant($p1, $p2);

        $x = 0;
        $y = 0;

        if ($quadrant === 1) {
            $x = $p2->x - $p1->x;
            $y = $p1->y - $p2->y;
        } elseif ($quadrant === 2) {
            $x = ($p1->x - $p2->x) * -1;
            $y = $p1->y - $p2->y;
        } elseif ($quadrant === 3) {
            $x = ($p1->x - $p2->x) * -1;
            $y = ($p2->y - $p1->y) * -1;
        } elseif ($quadrant === 4) {
            $x = $p2->x - $p1->x;
            $y = ($p2->y - $p1->y) * -1;
        }

        return new Vector($x, $y);
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
