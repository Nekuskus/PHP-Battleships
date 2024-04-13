<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Statki!</title>
    <link rel="stylesheet" href="index.css">
</head>
<body>
    <?php
    function getNameFromNumber($num) { // numeracja kolumny jak z excela, dla ładnego wyświetlania aby nie bawić się w różne symbole ascii
        $numeric = $num % 26;
        $letter = chr(65 + $numeric);
        $num2 = intval($num / 26);
        if ($num2 > 0) {
            return getNameFromNumber($num2 - 1) . $letter;
        } else {
            return $letter;
        }
    }

    enum Field: int {
        case Empty = 0b00;
        case Ship = 0b01;
        case Shot = 0b10;
        case ShipShot = 0b11;
    }

    class Battleships {
        public array $inner;
        public int $x_width;
        public int $y_width;
        function __construct(int $x_width = 10, int $y_width = 10, array $ships = [4 => 1, 3 => 2, 2 => 3, 1 => 4]) {
            $this->x_width = $x_width;
            $this->y_width = $y_width;
            $this->inner = [];
            for ($y=0; $y < $y_width; $y++) { 
                array_push($this->inner, []);
                for ($x=0; $x < $x_width; $x++) { 
                    array_push($this->inner[$y], Field::Empty);
                }
            }

            if(count($ships) == 0) { // do not generate ships, intended for manual setup when deserializing
                return;
            }

            // For best generation performance
            krsort($ships);
            
            
            // In case generation fails
            $backup = $ships;
            $safeguard = 100_000;
            
            start_gen:
            $fail_count = 0;
            while(count($ships) > 0) {
                $first_idx = array_key_first($ships);
                while ($ships[$first_idx] > 0) {
                    $num = rand(0, $x_width * $y_width - 1);
                    $y = intdiv($num, $x_width);
                    $x = $num % $x_width;
                    $curship = [];
                    if(!$this->place($y, $x)) {
                        if($fail_count == $safeguard) {
                            echo '<pre>';
                            print_r(get_defined_vars());
                            echo "&nbsp;0123456789\n";
                            for ($y=0; $y < 10; $y++) {
                                echo $y;
                                for ($x=0; $x < 10; $x++) {
                                    if($y == $next[0] && $x == $next[1]) {
                                        echo "<span class='cell full'>*</span>";
                                    }
                                    else if($this->inner[$y][$x] == Field::Ship) {
                                        echo "<span class='cell full'>X</span>";
                                    } else {
                                        echo "<span class='cell empty'>&nbsp;</span>";
                                    }
                                }
                                echo "<br>";
                            }
                            throw new DomainException();
                        }
                        do {
                            $num = rand(0, $x_width * $y_width - 1);
                            $y = intdiv($num, $x_width);
                            $x = $num % $x_width;
                            $fail_count++;
                        } while(!$this->place($y, $x) && $fail_count != $safeguard);
                    }

                    array_push($curship, [$y, $x]);

                    while (count($curship) < $first_idx) {
                        $next_guess = [];
                        if($y > 0 && !in_array([$y-1, $x], $curship, true)) {
                            array_push($next_guess, [$y-1, $x]);
                        }
                        if($y < $y_width-1 && !in_array([$y+1, $x], $curship, true)) {
                            array_push($next_guess, [$y+1, $x]);
                        }
                        if($x > 0 && !in_array([$y, $x-1], $curship, true)) {
                            array_push($next_guess, [$y, $x-1]);
                        }
                        if($x < $x_width-1 && !in_array([$y, $x+1], $curship, true)) {
                            array_push($next_guess, [$y, $x+1]);
                        }
                        if(count($next_guess) == 0) {
                            $this->inner = [];
                            for ($y_idx=0; $y_idx < $y_width; $y_idx++) { 
                                array_push($this->inner, []);
                                for ($x_idx=0; $x_idx < $x_width; $x_idx++) { 
                                    array_push($this->inner[$y_idx], Field::Empty);
                                }
                            }
                            $ships = $backup;
                            break;
                        }
                        $next = $next_guess[array_rand($next_guess)];
                        while(!$this->place($next[0], $next[1], $curship)) {
                            $fail_count++;
                            if($fail_count == $safeguard) {
                                echo '<pre>';
                                print_r(get_defined_vars());
                                echo "&nbsp;0123456789\n";
                                for ($y=0; $y < 10; $y++) {
                                    echo $y;
                                    for ($x=0; $x < 10; $x++) {
                                        if($y == $next[0] && $x == $next[1]) {
                                            echo "<span class='cell full'>*</span>";
                                        }
                                        else if($this->inner[$y][$x] == Field::Ship) {
                                            echo "<span class='cell full'>X</span>";
                                        } else {
                                            echo "<span class='cell empty'>&nbsp;</span>";
                                        }
                                    }
                                    echo "<br>";
                                }
                                throw new DomainException();
                            }
                            $to_remove = array_search($next, $next_guess);
                            if($to_remove !== false) {
                                array_splice($next_guess, $to_remove, 1);
                            }
                            
                            if(count($next_guess) == 0) {
                                $this->inner = [];
                                for ($y_idx=0; $y_idx < $y_width; $y_idx++) { 
                                    array_push($this->inner, []);
                                    for ($x_idx=0; $x_idx < $x_width; $x_idx++) { 
                                        array_push($this->inner[$y_idx], Field::Empty);
                                    }
                                }
                                $ships = $backup;
                                goto start_gen;
                            }
                            $next = $next_guess[array_rand($next_guess)];
                        }
                        array_push($curship, [$next[0], $next[1]]);
                        $y = $next[0];
                        $x = $next[1];
                    }
                    // echo "<pre>";
                    // print_r($ships);
                    $ships[$first_idx]--;
                    if ($ships[$first_idx] == 0) {
                        $ships = array_filter($ships, function ($key) use ($first_idx) {
                            return $key !== $first_idx;
                        }, ARRAY_FILTER_USE_KEY);
                        $first_idx = array_key_first($ships);
                        if($first_idx == null) {
                            break;
                        }
                    }
                }
            }
        }

        function place(int $y, int $x, array $excl = []) : bool {
            if($this->checkValidForPlace($y, $x, $excl)) {
                $this->inner[$y][$x] = Field::Ship;
                return true;
            }
            return false;
        }

        function checkValidForPlace(int $y, int $x, array $excl = []) : bool {
            if($y > 0) {
                if ($x > 0) {
                    if($this->inner[$y-1][$x-1] !== Field::Empty && !in_array([$y-1, $x-1], $excl, true)) {
                        return false;
                    }
                }

                if($this->inner[$y-1][$x] !== Field::Empty && !in_array([$y-1, $x], $excl, true)) {
                    return false;
                }

                if($x < $this->x_width-1) {
                    if($this->inner[$y-1][$x+1] !== Field::Empty && !in_array([$y-1, $x+1], $excl, true)) {
                        return false;
                    }
                }
            }

            if ($x > 0) {
                if($this->inner[$y][$x-1] !== Field::Empty && !in_array([$y, $x-1], $excl, true)) {
                    return false;
                }
            }

            if($this->inner[$y][$x] !== Field::Empty && !in_array([$y, $x], $excl, true)) {
                return false;
            }

            if($x < $this->x_width-1) {
                if($this->inner[$y][$x+1] !== Field::Empty && !in_array([$y, $x+1], $excl, true)) {
                    return false;
                }
            }

            if($y < $this->y_width-1) {
                if ($x > 0) {
                    if($this->inner[$y+1][$x-1] !== Field::Empty && !in_array([$y+1, $x-1], $excl, true)) {
                        return false;
                    }
                }

                if($this->inner[$y+1][$x] !== Field::Empty && !in_array([$y+1, $x], $excl, TRUE)) {
                    return false;
                }

                if($x < $this->x_width-1) {
                    if($this->inner[$y+1][$x+1] !== Field::Empty && !in_array([$y+1, $x+1], $excl, true)) {
                        return false;
                    }
                }
            }
            return true;
        }
        function serializeTemplate(): string { // returns a string representation of the *template* for this game, not including current game state, much more space-efficient due to binary state
            $s = $this->y_width . ','. $this->x_width . ';';
            $first = true;
            $mod = 0;
            $cur = 0b0;

            for ($y=0; $y < $this->y_width; $y++) {
                for ($x=0; $x < $this->x_width; $x++) { 
                    if($mod == 0 && !$first) {
                        $s .= chr($cur);
                        $cur = 0b0;
                    }
                    switch($this->inner[$y][$x]) {
                        case Field::Empty:
                        case Field::Shot:
                            //no need to increment
                            break;
                        case Field::Ship:
                        case Field::ShipShot:
                            $cur += 1 << $mod;
                            break;
                    }
                    $mod = ($mod + 1) % 8;
                    $first = false;
                }
            }

            $s .= chr($cur);

            return $s;
        }
        function serialize(): string { // returns a string representation of the *template* for this game, not including current game state, much more space-efficient due to binary state
            $s = $this->y_width . ','. $this->x_width . ';';

            for ($y=0; $y < $this->y_width; $y++) {
                for ($x=0; $x < $this->x_width; $x++) { 
                    $s .= $this->inner[$y][$x]->value;
                }
            }

            return $s;
        }
        static function unserializeTemplate(string $s): Battleships {
            $spl = explode(';', $s);
            $dims = explode(',', $spl[0]);
            $y_width = $dims[0];
            $x_width = $dims[1];

            $chars = str_split($spl[1]); //$length == 1 by default
            $chars = str_split(implode('', array_map(function ($c) {
                return str_pad(decbin(ord($c)), 8, '0', STR_PAD_LEFT);
            }, $chars)));
            

            $ret = new Battleships($y_width, $x_width, []);
            
            $total = 0; // easier and more efficient to inc than count in loop
            for ($y=0; $y < $y_width; $y++) {
                for ($x=0; $x < $x_width; $x++) { 
                    if($chars[$total] == '1') {
                        $ret->inner[$y][$x] = Field::Ship;
                    }
                    $total += 1;
                }
            }
            return $ret;
        }
        static function unserialize(string $s): Battleships {
            $spl = explode(';', $s);
            $dims = explode(',', $spl[0]);
            $y_width = $dims[0];
            $x_width = $dims[1];

            $chars = str_split($spl[1]); //$length == 1 by default
            $chars = array_map(function ($c) {
                return intval($c);
            }, $chars);
            

            $ret = new Battleships($y_width, $x_width, []);
            
            $total = 0; // easier and more efficient to inc than count in loop
            for ($y=0; $y < $y_width; $y++) {
                for ($x=0; $x < $x_width; $x++) { 
                    $ret->inner[$y][$x] = Field::from($chars[$total]);
                    
                    $total += 1;
                }
            }
            return $ret;
        }
    }

    function testSerializeTemplate() {
        $game = new Battleships();
        $game->inner[4][5] = Field::Ship;
        $s = $game->serializeTemplate();
        $reconstructed = Battleships::unserializeTemplate($s);
        // echo "<pre>";
        // var_dump($game->inner);
        // var_dump($reconstructed->inner);
        // echo "<pre>";
        echo $game->inner === $reconstructed->inner;
    }

    function testSerialize() {
        $game = new Battleships();
        $game->inner[5][4] = Field::Shot;
        $game->inner[9][1] = Field::ShipShot;
        $s = $game->serialize();
        $reconstructed = Battleships::unserialize($s);
        // echo "<pre>";
        // var_dump($game->inner);
        // var_dump($reconstructed->inner);
        // echo "<pre>";
        echo $game->inner === $reconstructed->inner;
    }

    class UBoots { // Battleships in 3d, strzał w "kuli" (środek + wypustki w każdym major kierunku, czyli bez przekątnych)
        
    } 
    

    // $game = new Battleships();

    // try {
    //     $game = new Battleships();
    //     // $game = new Battleships(10, 10, [1 => 10]);
    //     // $game = new Battleships(10, 10, []);
    // } catch (\Throwable $th) {
    //     echo $th ."<br>";
    //     echo "Failed to generate a Battleships game, please make sure your parameters allow for a valid Battleship configuration.<br>";
    //     // exit();
    // }

    // $game->place(3, 9);
    
    // $game->place(4, 9, [[3, 9]]);

    // echo "&nbsp;0123456789<br>";
    // for ($y=0; $y < 10; $y++) {
    //     echo $y;
    //     for ($x=0; $x < 10; $x++) {
    //         if($game->inner[$y][$x] == Field::Ship) {
    //             echo "<span class='cell full'>X</span>";
    //         } else {
    //             echo "<span class='cell empty'>&nbsp;</span>";
    //         }
    //     }
    //     echo "<br>";
    // }

    ?>
</body>
</html>