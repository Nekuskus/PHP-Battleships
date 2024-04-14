<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Statki!</title>
    <link rel="stylesheet" href="index.css">
    <script src="index.js" defer></script>
</head>
<body>
    <div id="nav">
        <ul>
            <a href="?route=set"><li>Generuj nowe...</li></a>
            <!-- TE MAJĄ ONCLICK -->
            <a onclick="saveTemplate()"><li id='save-template'>Zapisz templatkę...</li></a>
            <a onclick="saveState()"><li id='save-state'>Zapisz stan...</li></a>
            <a href="?route=load"><li id='save-template'>Wczytaj..</li></a>
        </ul>
    </div>
    <?php
    $start = microtime(true);
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
        public int $z_width;
        function __construct(int $x_width = 10, int $y_width = 10, int $z_width = 1, array $ships = [4 => 1, 3 => 2, 2 => 3, 1 => 4]) {
            $this->x_width = $x_width;
            $this->y_width = $y_width;
            $this->z_width = $z_width;
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
                        $fail_count++;
                        if($fail_count == $safeguard) {
                            throw new DomainException();
                        }
                        do {
                            $num = rand(0, $x_width * $y_width - 1);
                            $y = intdiv($num, $x_width);
                            $x = $num % $x_width;
                            $fail_count++;
                            if($fail_count == $safeguard) {
                                throw new DomainException();
                            }
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
                            $fail_count++;
                            if($fail_count == $safeguard) {
                                throw new DomainException();
                            }
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
                        while(!$this->place($next[0], $next[1], $curship)) {
                            $fail_count++;
                            if($fail_count == $safeguard) {
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
            
            // Sanity checking for missed_cases
            $field_count = 0;

            foreach($backup as $key => $count) {
                $field_count += $key * $count;
            }

            $found = 0;
            for ($y_idx=0; $y_idx < $y_width; $y_idx++) { 
                for ($x_idx=0; $x_idx < $x_width; $x_idx++) {
                    if($this->inner[$y_idx][$x_idx] == Field::Ship) $found += 1;
                }
            }

            if($found != $field_count) {
                throw new DomainException();
            }
        }


        // MACRO FOR DEBUG ERROR PRINTING
        // echo '<pre>';
        // print_r(get_defined_vars());
        // echo "&nbsp;0123456789\n";
        // for ($y=0; $y < 10; $y++) {
        //     echo $y;
        //     for ($x=0; $x < 10; $x++) {
        //         if($y == $next[0] && $x == $next[1]) {
        //             echo "<span class='cell full'>*</span>";
        //         }
        //         else if($this->inner[$y][$x] == Field::Ship) {
        //             echo "<span class='cell full'>X</span>";
        //         } else {
        //             echo "<span class='cell empty'>&nbsp;</span>";
        //         }
        //     }
        //     echo "<br>";
        // }

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
            $s = $this->z_width. ',' .$this->y_width . ','. $this->x_width . ';';
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
            $s = $this->z_width. ',' .$this->y_width . ','. $this->x_width . ';';

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
            $z_width = $dims[0];
            $y_width = $dims[1];
            $x_width = $dims[2];

            $chars = str_split($spl[1]); //$length == 1 by default
            $chars = str_split(implode('', array_map(function ($c) {
                return str_pad(decbin(ord($c)), 8, '0', STR_PAD_LEFT);
            }, $chars)));

            $ret = new Battleships($y_width, $x_width, $z_width, []);

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
            $z_width = $dims[0];
            $y_width = $dims[1];
            $x_width = $dims[2];

            $chars = str_split($spl[1]); //$length == 1 by default
            $chars = array_map(function ($c) {
                return intval($c);
            }, $chars);
            

            $ret = new Battleships($y_width, $x_width, $z_width, []);
            
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

    // function testSerializeTemplate() {
    //     $game = new Battleships();
    //     $game->inner[4][5] = Field::Ship;
    //     $s = $game->serializeTemplate();
    //     $reconstructed = Battleships::unserializeTemplate($s);
    //     // echo "<pre>";
    //     // var_dump($game->inner);
    //     // var_dump($reconstructed->inner);
    //     // echo "<pre>";
    //     echo $game->inner === $reconstructed->inner;
    // }

    // function testSerialize() {
    //     $game = new Battleships();
    //     $game->inner[5][4] = Field::Shot;
    //     $game->inner[9][1] = Field::ShipShot;
    //     $s = $game->serialize();
    //     $reconstructed = Battleships::unserialize($s);
    //     // echo "<pre>";
    //     // var_dump($game->inner);
    //     // var_dump($reconstructed->inner);
    //     // echo "<pre>";
    //     echo $game->inner === $reconstructed->inner;
    // }

    function settings_panel() {
        echo "<div id='settings-panel'>";
        echo "<h1>Parametry nowej gry w statki</h1>";
        echo "<form id='settings' action='?route=gen' method='GET'>";
        echo "<div><label for='x-width'>Szerokość x</label> <input name='x-width' type='number' min='1' step='1' value='10'></div><br>";
        echo "<div><label for='y-width'>Szerokość y</label> <input name='y-width' type='number' min='1' step='1' value='10'></div><br>";
        echo "<div><label for='z-width'>Szerokość z</label> <input name='z-width' type='number' min='1' step='1' value='1'></div><br>";
        echo "<div><label for='length'>Długość statku</label> <input name='length' type='number' min='1' step='1' value='1'></div><br>";
        echo "<div><label for='count'>Ilość statku</label> <input name='count' type='number' min='1' step='1' value='1'></div><br>";
        echo "<button type='button' id='add-ship'>Dodaj statek...</button><br>";
        echo "<p>Statki: [<span id='statki'></span>]</p><br><br>";
        echo "<select name='ui-type'><option value='play' selected>Graj</option><option value='displ'>Wyświetl</option></select>";
        echo "<button type='submit'>Generuj</button>";
        echo "</form>";
        echo "</div>";
    }

    function create_game(string $next) {
        if($_SERVER['REQUEST_METHOD'] !== 'GET') {
            render_play(new Battleships()); // default args
            return;
        }
        
        if(isset($_GET['file'])) {
            try {
                $s = file_get_contents('.\\saves\\' . $_GET['file']);
                $dims_str = explode(';', $_GET['file'])[0];
                if(str_contains($dims_str, 'TEMPLATE')) {
                    $s = $dims_str . ';' . $s;
                    $s = str_replace('TEMPLATE', '', $s);
                    $game = Battleships::unserializeTemplate(Battleships::unserializeTemplate($s)->serializeTemplate());
                } else {
                    $s = $dims_str . ';' . $s;
                    $game = Battleships::unserialize($s);
                }
                $template = base64_encode('TEMPLATE' . $game->serializeTemplate());
                $json = json_encode($game->inner);
                echo "<script>let TEMPLATE_STR = '{$template}';
                let GAME_JSON = {$json};</script>";
                if($next == 'play') {
                    render_play($game);
                } else {
                    render_displ($game);
                }
            } catch (\Throwable $th) {
                echo "<div id='game-wrapper'><div id='game'>";
                echo "Generacja planszy nieudana, spróbuj ponownie (auto: za 3s), lub popraw swoje dane aby umożliwić stworzenie <i>poprawnej</i> planszy do gry w statki.";
                echo "<br>Błąd: {$th->__toString()}";
                echo "</div></div>";
                echo "<script>
                
                new Promise(res => setTimeout(res, 3000)).then(() => {
                location.reload();
                });
                </script>";
            }
            return;
        }

        if(isset($_GET['x-width'])) {
            $x_width = intval($_GET['x-width']) ?? 10;
        } else {
            $x_width = 10;
        }
        
        if(isset($_GET['y-width'])) {
            $y_width = intval($_GET['y-width']) ?? 10;
        } else {
            $y_width = 10;
        }
        
        if(isset($_GET['z-width'])) {
            $z_width = intval($_GET['z-width']) ?? 1;
        } else {
            $z_width = 1;
        }
        
        if(isset($_GET['ships'])) {
            $ships = $_GET['ships'] ?? ['4->1,3->2,2->3,1->4'];
            $spl = explode(',', $ships);
            $ships_parsed = [];
            foreach($spl as $pattern) {
                $nums = explode('->', $pattern);
                $a = intval($nums[0]);
                $b = intval($nums[1]);
                $ships_parsed[$a] = $b;
            }
        } else {
            $ships_parsed = [4 => 1, 3 => 2, 2 => 3, 1 => 4];
        }
        try {
            $game = new Battleships($x_width, $y_width, $z_width, $ships_parsed);
            $template = base64_encode('TEMPLATE' . $game->serializeTemplate());
            $json = json_encode($game->inner);
            echo "<script>let TEMPLATE_STR = '{$template}';
            let GAME_JSON = {$json};</script>";
            if($next === 'play') {
                render_play($game);
            } else {
                render_displ($game);
            }
        } catch (\Throwable $th) {
            echo "<div id='game-wrapper'><div id='game'>";
            echo "Generacja planszy nieudana, spróbuj ponownie (auto: za 3s), lub popraw swoje dane aby umożliwić stworzenie <i>poprawnej</i> planszy do gry w statki.";
            echo "<br>Błąd: {$th->__toString()}";
            echo "</div></div>";
            echo "<script>
            
            new Promise(res => setTimeout(res, 3000)).then(() => {
            location.reload();
            });
            </script>";
        }
    }

    function render_play(Battleships $game) {
        $x1 = $game->x_width + 1;
        $y1 = $game->y_width + 1;
        echo "<script>
        document.documentElement.style.setProperty('--columns', `repeat({$x1}, 1fr)`);
        document.documentElement.style.setProperty('--rows', `repeat({$y1}, 1fr)`);
        </script>";
        echo "<div id='game-wrapper'><div id='game'>";
        echo "<div class='layer'>";
        echo "<div class='cell empty'></div>";
        for ($x=0; $x < $game->x_width; $x++) { 
            echo "<div class='cell label'><span class='coord'>".($x+1)."</span></div>";
        }
        for($y=0; $y < $game->y_width; $y++) {
            echo "<div class='cell label'><span class='coord'>".getNameFromNumber($y)."</span></div>";
            for ($x=0; $x < $game->x_width; $x++) { 
                echo "<div class='cell field empty'></div>";
            }
        }
        echo "</div>";
        echo "</div></div>";
    }

    function render_displ(Battleships $game) {
        $x1 = $game->x_width + 1;
        $y1 = $game->y_width + 1;
        echo "<script>
        document.documentElement.style.setProperty('--columns', `repeat({$x1}, 1fr)`);
        document.documentElement.style.setProperty('--rows', `repeat({$y1}, 1fr)`);
        </script>";
        echo "<div id='game-wrapper'><div id='game'>";
        echo "<div class='layer'>";
        echo "<div class='cell empty'></div>";
        for ($x=0; $x < $game->x_width; $x++) { 
            echo "<div class='cell label'><span class='coord'>".($x+1)."</span></div>";
        }
        for($y=0; $y < $game->y_width; $y++) {
            echo "<div class='cell label'><span class='coord'>".getNameFromNumber($y)."</span></div>";
            for ($x=0; $x < $game->x_width; $x++) { 
                switch($game->inner[$y][$x]) {
                    case Field::Ship:
                        echo "<div class='cell ship'></div>";
                        break;
                    case Field::Shot:
                        echo "<div class='cell shot'></div>";
                        break;
                    case Field::ShipShot:
                        echo "<div class='cell shipshot'></div>";
                        break;
                    case Field::Empty:
                        echo "<div class='cell empty'></div>";
                        break;
                }
            }
        }
        echo "</div>";
        echo "</div></div>";
    }

    function load() {
        echo "<div id='game-wrapper'><div id='game'>";
        echo "Istniejące zapisy<br>";
        echo "<select id='select-savefile'>";
        $first = true;
        foreach(scandir(".\\saves") as $file) {
            if($file == '.' || $file == '..') continue;
            if(!$first) {
                echo "<option val='$file'>$file</option>";
            } else {
                $first = false;
                echo "<option val='$file' selected>$file</option>";
            }
        }
        echo "</select><br>";
        echo "<select id='ui-type'><option value='play' selected>Graj</option><option value='displ'>Wyświetl</option></select><br>";
        echo "<button type='button' onclick='loadfile()'>Wczytaj</button>";
        echo "</div></div>";
    }

    function save() {
        if(isset($_GET['payload'])) {
            $contents = base64_decode(str_replace('-', '=', $_GET['payload']));
            $spl = explode(';', $contents);
            $filename = array_shift($spl) . ';' . time();
            $contents = implode(';', $spl);
            $file = fopen(".\\saves\\{$filename}", "w");
            if(!str_contains($filename, 'TEMPLATE')) {
                $contents = json_decode($contents);
                $s = '';
                $dims = explode(',', explode(';', $filename)[0]);
                $z_width = intval($dims[0]);
                $y_width = intval($dims[1]);
                $x_width = intval($dims[2]);
                for ($y=0; $y < $y_width; $y++) { 
                    for ($x=0; $x < $x_width; $x++) {
                        $s .= $contents[$y][$x];
                    }
                }
                $contents = $s;
            }
            if (fwrite($file, $contents) !== false) {
                echo "<div id='game-wrapper'><div id='game'>";
                echo "Pomyślnie zapisano do .\\saves\\{$filename} na serwerze.";
                echo "</div></div>";
            } else {
                echo "<div id='game-wrapper'><div id='game'>";
                echo "Błąd zapisu do .\\saves\\{$filename} na serwerze.";
                echo "</div></div>";
            }
        } else {
            echo '<script>
            window.location.href = window.location.toString().split('/').slice(0, -1).join('/');
            </script>';
        }
    }

    if(isset($_GET['route'])) {
        switch($_GET['route']) {
            case 'play':
                create_game('play');
                break;
            case 'displ': 
                create_game('displ');
                break;
            case 'set':
                settings_panel();
                break;
            case 'load':
                load();
                break;
            case 'save':
                save();
                break;
        }
    } else {
        settings_panel();
    }

    $end = microtime(true);
    
    $taken = number_format(($end - $start)*1000, 0);
    echo "<script>
    let nav = document.querySelector('div#nav ul');
    let child = document.createElement('a');
    child.setAttribute('id', 'time-taken');
    child.innerHTML = '<li><i>PHP took {$taken}ms to finish...</i></li>'
    nav.appendChild(child);
    </script>";
    ?>
</body>
</html>