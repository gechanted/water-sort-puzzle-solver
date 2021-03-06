<?php
require_once __DIR__ . '/Tube.php';
require_once __DIR__ . '/ProgressRecorder.php';

class Board
{


    private HashLog $generalLog;
    private ProgressRecorder $recorder;

    /**
     * @var Tube[]
     */
    private array $tubes;
    private int $deepness;
    private bool $echoPath;
    private bool $echoTime;

    /**
     * @param Tube[] $tubes
     * @param ProgressRecorder $recorder
     * @param int $deepness
     * @param HashLog|null $generalLog
     * @param bool $echoPath
     * @param bool $echoTime
     */
    public function __construct(array $tubes, ProgressRecorder $recorder, int $deepness = 0, HashLog $generalLog = null,
                                bool $echoPath = false, bool $echoTime = false)
    {
        $this->tubes = $tubes;
        $this->recorder = $recorder;
        $this->deepness = $deepness;
        $this->echoPath = $echoPath;
        $this->echoTime = $echoTime;
        $this->generalLog = $generalLog ?: new HashLog();
    }

    public function isSolved(): bool
    {
        foreach ($this->tubes as $tube) {
            if ($tube->isSolved() === false) {
                return false;
            }
        }

        return true;
    }

    public function solve(): bool
    {
        if ($this->echoTime) { Timer::time(str_repeat('  ', $this->deepness)); }
        //detect completion
        $isSolved = $this->isSolved();

        if ($isSolved) {
            $this->recorder->recordBoard($this);
            return true;
        }

        //calculate the possible moves
        foreach ($this->tubes as $k1 => $tube1) {
            foreach ($this->tubes as $k2 => $tube2) {
                if ($tube1 !== $tube2) { //prevent putting sth from itself to itself
                    if ($tube2->canReceive($tube1->getExtractable())) {
                        if ($this->echoPath) { echo str_repeat('  ', $this->deepness) . $tube1->getNr() . ' into ' . $tube2->getNr() . PHP_EOL;}
                        //if there is a possible move: do it
                        //in a new thread, to not change this board
                        $newBoard = $this->clone();
                        $result = $newBoard->solvingMove($k1, $k2);
                        //if the solution is correct
                        if ($result) {
                            //log this part in the solving process
                            $this->recorder->recordBoard($this);
                            //and pass on the good news
                            return true;
                        }

                    }
                }
            }
        }
        return false;
    }

    public function solvingMove($tube1Index, $tube2Index): bool
    {
        $tube1 = $this->tubes[$tube1Index];
        $tube2 = $this->tubes[$tube2Index];

        $extract = $tube1->getExtractable();
        $tube2->doReceive($extract);
        $tube1->doExtract();

        //make a short identifier for this board constellation
        //to ensure no infinite loops (and better calculation times)
        $hash = $this->hash();
        if ($this->generalLog->search($hash)) {
            return false;
        }
        $this->generalLog->add($hash);

        //this board was cloned and has now changed its content
        //start solving further (recursion call)
        $result = $this->solve();
        //if the solution is correct
        if ($result) {
            //log this part in the solving process
            //I know here shouldn't be any text generation, but it's for the time being easier this way
            $this->recorder->recordMove($tube1->getNr() . ' -> ' . $extract[0]->getColorName() . ' -> ' . $tube2->getNr());
            return true;
        }
        if ($this->echoPath) { echo str_repeat('  ', $this->deepness) . ' <- fail' . PHP_EOL;}
        return false;
    }



    public function clone(bool $newSubObjects = false): Board
    {
        $tubeArr = [];
        foreach ($this->tubes as $tube) {
            $tubeArr[] = clone $tube;
        }

        return new Board($tubeArr,
            $newSubObjects ? new ProgressRecorder() : $this->recorder,
            $newSubObjects ? $this->deepness : $this->deepness +1,
            $newSubObjects ? new HashLog() : $this->generalLog,
            $this->echoPath, $this->echoTime);
    }

    public function hash(): string
    {
        $hashes = [];
        foreach ($this->tubes as $tube) {
            $hashes[] = intval($tube->hash());
        }
        sort($hashes);

        $wholeHash = '';
        foreach ($hashes as $hash) {
            $wholeHash .= str_pad($hash, 4, "0", STR_PAD_LEFT);
        }
        return $wholeHash;
    }

    /**
     * @return Tube[]
     */
    public function getTubes(): array
    {
        return $this->tubes;
    }

    public function getRecorder(): ProgressRecorder
    {
        return $this->recorder;
    }

    public function getDeepness(): int
    {
        return $this->deepness;
    }
}