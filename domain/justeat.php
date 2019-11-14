<?php

require_once  __DIR__.'/../database/database.php';
require_once  __DIR__.'/../vendor/autoload.php';
require_once  __DIR__.'/../data/data.php';
require_once  __DIR__.'/../config/config.php';
require_once  __DIR__.'/../logs/logger.php';

// require_once __DIR__.DIRECTORY_SEPARATOR.'..'.DIRECTORY_SEPARATOR.'..'.DIRECTORY_SEPARATOR.'vendor'.DIRECTORY_SEPARATOR.'autoload.php';
// require_once __DIR__.DIRECTORY_SEPARATOR.'..'.DIRECTORY_SEPARATOR.'database'.DIRECTORY_SEPARATOR.'database.php';

// require_once __DIR__.DIRECTORY_SEPARATOR.'..'.DIRECTORY_SEPARATOR.'data'.DIRECTORY_SEPARATOR.'data.php';
// require_once __DIR__.DIRECTORY_SEPARATOR.'..'.DIRECTORY_SEPARATOR.'config'.DIRECTORY_SEPARATOR.'config.php';
// require_once __DIR__.DIRECTORY_SEPARATOR.'..'.DIRECTORY_SEPARATOR.'logs'.DIRECTORY_SEPARATOR.'logger.php';

use Symfony\Component\DomCrawler\Crawler;
use Goutte\Client;

class justEat {
    
    public $restaurant_id;
    public $user_id = 0;

    public $city = 'Birmingham';
    public $postcode_area = 'B';

    public $connection;

    //Get All Restaurant PostCodes For Given City
    public function postcodes(){

        global $logger;
        $client = new Client();

        $output = array();

        $city = $this->city;
        $postcode_area = $this->postcode_area;

        for ($i = 1; $i <= 99;$i++){

            $url = "https://www.just-eat.co.uk/area/$postcode_area$i-$city";
    
            $crawler = $client->request('GET', $url );
            
            $restaurant = $crawler->filter('section.c-listing-item');
    
            if(sizeof($restaurant) !== 0){
                
                $logger->debug("PostCode Download $url Found");

                $path = __DIR__."/../resources/postcodes/$postcode_area$i-$city.html";
                $output[] = $path;
                file_put_contents($path,$crawler->html());
    
            }
            else {
                $logger->debug("PostCode $url, no restaurants found");
            }

            sleep(10);

        }

        return $output;

    }

    public function shorten($string){

        $string = str_replace('<br><br>','<br>',$string);
        $output =  trim(preg_replace("/\s+/", " ", str_replace('<br>','\n',$string)));
        return htmlentities(html_entity_decode($output),ENT_QUOTES);

    }

    public function restaurants($postcodes){
        
        global $database,$output,$logger;
        $database = new Database();
        $output = array();

        // print_r($postcodes);
        foreach($postcodes as $postcode){

            preg_match('/^\.+$/', $postcode, $matches);

            if(!$matches){

                // $file = __DIR__."resources/postcodes/$postcode";
                /////////////////////////////////////
                $file = $postcode;

                if (file_exists($file)) {
                    // echo $file."\n";

                    $html = file_get_contents($file);

                    $crawler = new Crawler($html);

                    $crawler->filter('section[data-restaurant-id]')->each(function(Crawler $node, $i){

                        global $database,$output,$logger;

                        $online_id = $node->attr('data-restaurant-id');
                        $url       = 'https://www.just-eat.co.uk'.$node->filter('a.c-listing-item-link')->eq(0)->attr('href');

                        if(!array_key_exists($url,$output)){

                            $result = $database->query("select * from restaurant where online_id='$online_id'");

                            if($result->num_rows){
                                $logger->debug("Restaurant Exists, $url");
                                //Update or Skip
                                // echo "Found";
                            }
                            else {
                                $logger->debug("Restaurant New, $url");
                                $output[$url] =  1;
                            }

                        }

                        
                    });

                }

                else {
                    $logger->error("Postcode passed not found",array('postcode' => $postcode));
                }

            }

        }

        return array_keys($output);

    }

    public function menu($url) {

        global $restaurant,$categories,$logger;
        $restaurant = new data();

        $logger->debug("Fetching menu for, $url");
        $client = new Client();
        $crawler = $client->request('GET', $url);
        $html = $crawler->html();

        preg_match('/https:\/\/www\.just-eat\.co\.uk\/(.+)\/menu/',$url,$matches);
        file_put_contents(__DIR__.'/../resources/restaurants/'.$matches[1]."_menu.html",$html);

        //////////////////////////////////////////////////////////////////////////////////////////////

        // $html = file_get_contents(__DIR__.'/../resources/restaurants/restaurants-caspian-grill-and-pizza-birmingham.html');
        // $crawler = new Crawler($html);

        /////////////////////////////////////////////////////////////////////////////////////////////


        $categories = array();

        $crawler->filter('.category')->each(function(Crawler $node, $i){
            
            global $category,$categories,$logger;
            $category = new data();
            $category->foods = array();

            $node->filter('h3')->each(function(Crawler $node, $i){
                global $category,$logger;
                $category_name  = $this->shorten($node->text());
                $category->name = $category_name;
                $logger->debug("Category: $category_name");
            });
            
            if ($node->filter('.categoryDescription')->count() !== 0) {
                
                $node->filter('.categoryDescription')->each(function(Crawler $node, $i){
                    global $category;
                    $category->description = $this->shorten($node->html());
                });

            } else {
                global $category;
                $category->description = '';
            }
            
            preg_match('/Popular|Recommended|offer| New/i',$category->name,$matches);


            if (!$matches) {

                $node->filter('.products')->each(function(Crawler $node, $i){
                    
                    //With Sub Category
                    $node->filter('.product.withSynonyms')->each(function(Crawler $node, $i){

                        global $food,$category;
                        $food = new data();
                        $food->options = [];
                        $food->price = 0;

                        $node->filter('.information')->each(function(Crawler $node, $i)
                        {
                             
                            $node->filter('.name')->each(function(Crawler $node, $i)
                            {
                                global $food;
                                $food->name = $this->shorten($node->text());
                            });
                            
                            if ($node->filter('.description')->count() !== 0) {
                                $node->filter('.description')->each(function(Crawler $node, $i)
                                {
                                    global $food;
                                    $food->description = $this->shorten($node->html());
                                });
                            } else {
                                global $food;
                                $food->description = '';
                            }
                            
                        });
                        
                        
                        $node->filter('.details')->each(function(Crawler $node, $i){

                            global $food,$subfood;
                            $subfood = new data();
                            
                            $node->filter('.synonymName')->each(function(Crawler $node, $i)
                            {
                                global $subfood;
                                $subfood->name = $this->shorten($node->text());
                            });
                            
                            $node->filter('.price')->each(function(Crawler $node, $i)
                            {
                                global $subfood;
                                $subfood->price = $this->shorten(str_replace('£', '', $node->text()));
                            });

                            $food->options[] = $subfood;

                        });
                        
                        $category->foods[] = $food;
                    });
                    
                    //Without Sub Category
                    $node->filter('.product:not(.withSynonyms)')->each(function(Crawler $node, $i){
                        
                        global $food,$category;
                        $food = new data();
                        $food->options = null;

                        $node->filter('.information')->each(function(Crawler $node, $i)
                        {
                            
                            $node->filter('.name')->each(function(Crawler $node, $i)
                            {
                                global $food;
                                $food->name = $this->shorten(($node->text()));
                            });
                            
                            if ($node->filter('.description')->count() !== 0) {
                                $node->filter('.description')->each(function(Crawler $node, $i){
                                    global $food;
                                    $food->description = $this->shorten($node->html());
                                });
                            } else {
                                global $food;
                                $food->description = '';
                            }
                            
                            
                        });
                        
                        $node->filter('.price')->each(function(Crawler $node, $i){
                            global $food;
                            $food->price = $this->shorten(str_replace('£', '', $node->text()));
                        });
                        
                        $category->foods[] = $food;

                    });
                    
                });

                $categories[] =  $category;

            }

        });
        
        $restaurant->categories = $categories;
        
        return $restaurant;
    }

    public function info($url){

        global $restaurant,$logger;

        $restaurant = new data();
        $restaurant->url = $url;
        $client = new Client();
        $crawler = $client->request('GET', $url);
        $html = $crawler->html();

        $error = $crawler->filter('.c-search__error-text')->count();

        if($error){
            $logger->error("$url doesn't really exist");
            return false;
        }

        $logger->debug("Fetching info,$url");

        preg_match('/https:\/\/www\.just-eat\.co\.uk\/(.+)/',$url,$matches);
        file_put_contents(__DIR__.'/../resources/restaurants/'.$matches[1]."_info.html",$html);

        $crawler->filter('.restaurantOverview ')->each(function(Crawler $node, $i){

            global $restaurant;

            $node->filter('h1')->each(function(Crawler $node, $i){
                global $restaurant,$logger;
                $restaurant->name = $this->shorten($node->text());
                $logger->notice("New Restaurant: ".$restaurant->name);
            });

            $restaurant->online_id = $node->attr('data-restaurant-id');
            
            $node->filter('.cuisines')->each(function(Crawler $node, $i){

                global $restaurant;
                $restaurant->categories = $this->shorten($node->text());
                
            });
            
            $node->filter('.address')->each(function(Crawler $node, $i){

                $location = explode(",", $this->shorten($node->text()));
                $length   = count($location);
                
                global $restaurant;
                
                switch ($length) {
                    case $length === 3:
                        $restaurant->address1 = $this->shorten($location[0]);
                        $restaurant->address2 = '';
                        $restaurant->address3 = '';
                        $restaurant->postcode     = $this->shorten($location[2]);
                        $restaurant->city         = $this->shorten($location[1]);
                        break;
                    
                    case $length === 4:
                        $restaurant->address1 = $this->shorten($location[0]);
                        $restaurant->address2 = $this->shorten($location[1]);
                        $restaurant->address3 = '';
                        $restaurant->postcode     = $this->shorten($location[3]);
                        $restaurant->city         = $this->shorten($location[2]);
                        break;
                    
                    case $length === 5:
                        $restaurant->address1 = $this->shorten($location[2]);
                        $restaurant->address2 = $this->shorten($location[0]);
                        $restaurant->address3 = $this->shorten($location[1]);
                        
                        $restaurant->postcode = $this->shorten($location[4]);
                        $restaurant->city     = $this->shorten($location[3]);
                        break;
                }
                
                $restaurant->country = 'United Kingdom';
                $restaurant->county  = 'West Midlands';

            });
            
        });

        $crawler->filter('.restaurantMenuDescription')->each(function(Crawler $node, $i)
        {
            global $restaurant;
            $restaurant->description = $this->shorten($node->text());
        });
        
        $crawler->filter('.restaurantOpeningHours table')->each(function(Crawler $node, $i)
        {
            
            global $restaurant;
            
            $restaurant->hours = '[';
            
            $node->filter('tr')->each(function(Crawler $node, $i)
            {
                global $restaurant;
                
                $day  = $node->filter('td')->eq(0)->text();
                $time = $node->filter('td')->eq(1)->text();
                
                $hours = explode(' - ', $time);
                
                $open  = $hours[0];
                $close = $hours[1];
                
                $restaurant->hours .= <<<END
{
"day": "$day",
"open": "$open",
"close": "$close"
},
END;
                
            });
            
            $restaurant->hours .= ']';
            
            $restaurant->hours = $this->shorten(str_replace(',]', ']', $restaurant->hours));
            
        });

        return $restaurant;
    }

    public function food($categories){

        global $database;
        $database = new Database();
        $connection = $database->connection;
        $restaurant_id = $this->restaurant_id;

        foreach($categories as $category){
            $catName = $category->name;
            $catDescription = $category->description;

            $database->query("INSERT INTO category (name, description) VALUES ('$catName', '$catDescription')");
            $catId = $connection->insert_id;

            //Insert into db and category_id and use to insert food
            foreach($category->foods as $food){
                $foodName = $food->name;
                $foodDescription = $food->description;
                $foodPrice = 0;
                if($food->price){
                    $foodPrice = $food->price;
                }

                $database->query("INSERT INTO food (name, description,price,num_ratings,overall_rating,restaurant_id,category_id) 
                VALUES ('$foodName','$foodDescription','$foodPrice','0',null,'$restaurant_id','$catId')");
                
                $foodId = $connection->insert_id;

                if( $food->options ){

                    foreach($food->options as $option){
                        $optionName = $option->name;
                        $optionPrice = $option->price;
    
                        $database->query("INSERT into sub_category(name,price,food_id) values('$optionName','$optionPrice','$foodId')");
    
                    }

                }

            }
        }

    }

    public function insert($restaurant){

        global $database,$logger;
        $database = new Database();

        $name         = $restaurant->name;
        $hours        = $restaurant->hours;
        $categories   = $restaurant->categories;
        $online_id    = $restaurant->online_id;
        $user_id      = $this->user_id;

        $address1     = $restaurant->address1;
        $address2     = $restaurant->address2;
        $address3     = $restaurant->address3;
        $postcode     = $restaurant->postcode;
        $county       = $restaurant->county;
        $country      = $restaurant->country;
        $url          = $restaurant->url;
        $city         = $this->city;

        $logo  = "https://d30v2pzvrfyzpo.cloudfront.net/uk/images/restaurants/$online_id.gif";
        
        file_put_contents(__DIR__."/../resources/logos/$online_id.gif", fopen($logo, 'r'));

        $database->query("insert into restaurant(name,opening_hours,categories,user_id,online_id,url) 
        values('$name','$hours','$categories','$user_id','$online_id','$url')");

        $logger->debug("Insert Restaurant, $name",array('url' => $url));

        $restaurant_id =  $database->connection->insert_id;
        $this->restaurant_id = $restaurant_id;

        $database->query("insert into location (address_line1,address_line2,address_line3,postcode,city,county,country,restaurant_id) 
        values ('$address1','$address2','$address3','$postcode','$city','$county','$country','$restaurant_id')");

    }

    //Restaurant
    public function restaurant($menuUrl){

        global $logger;

        $logger->notice("Scraping Restaurant $menuUrl");
        $infoUrl = str_replace('/menu', '', $menuUrl);
        
        $this->error($infoUrl);

        $info = $this->info($infoUrl);
        
        if($info){

            $menu = $this->menu($menuUrl);

            $this->insert($info);
            $this->food($menu->categories);
    
            sleep(60);

        }

    }

    public function new($new_restaurants){

        global $logger;
        $city = $this->city;
        $logger->notice('New Restaurants Added');
        $list = json_encode($new_restaurants);
        file_put_contents("list/$city.json",$list);

    }

    public function exists($url){
        global $database,$logger;
        $database = new Database();

        $logger->debug("Checking If Present Already $url");

        $results = $database->query("select * from restaurant where url='$url'");
        if($results->num_rows){
            $logger->error("Possible Issue, shouldn't exist in db?");
            return true;
        }
        $logger->debug("New Insert Doesn't Exist As Expected");
        return false;
    }

    //If previous fails due to error, delete all relating to it and reset incrementer
    public function error($url){

        global $logger;

        $database = new Database;
        $result = $database->query("SELECT * from restaurant where url='$url'");

        $logger->notice('Deleting Older Restaurant Data',array('query' =>"SELECT * from restaurant where url='$url'" ));

        if($result->num_rows){
            $row  = $result->fetch_assoc();
            $restaurant_id = $row['id'];

            $database->query("DELETE FROM location where restaurant_id='$restaurant_id'");
            $database->query("ALTER TABLE location AUTO_INCREMENT = 1");

            $subresults = $database->query("SELECT * from food where restaurant_id='$restaurant_id' order by id asc limit 1");
            if($subresults->num_rows){
                $subrow  = $subresults->fetch_assoc();
                $food_id = $subrow['id'];
                $category_id = $subrow['category_id'];

                $database->query("DELETE from sub_category where food_id >= '$food_id'");
                $database->query("ALTER TABLE sub_category AUTO_INCREMENT = 1");

                $database->query("DELETE from food where restaurant_id='$restaurant_id'");
                $database->query("ALTER TABLE food AUTO_INCREMENT = 1");

                $database->query("DELETE from category where id >= '$category_id'");
                $database->query("ALTER TABLE category AUTO_INCREMENT = 1");

            }
            else {
                //Failed on category,Delete last one
                $database->query("delete from category order by id desc limit 1");
                $database->query("ALTER TABLE category AUTO_INCREMENT = 1");
            }

            
            //Failed to inserting location, no food or categories present
            $database->query("DELETE FROM restaurant where id='$restaurant_id'");
            $database->query("ALTER TABLE restaurant AUTO_INCREMENT = 1");

        }

    }

}

?>