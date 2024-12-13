<?php
$servername = "localhost";
$username = "root";
$password = "";
$dbname = "africa_geo_junior";

$conn = new mysqli($servername, $username, $password, $dbname);

if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

$conn->set_charset("utf8");

$feedbackMessage = "";
$selectedCountry = null;
$perPage = 5;
$page = isset($_GET['page']) && is_numeric($_GET['page']) ? (int)$_GET['page'] : 1;

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    if (isset($_POST['add_country'])) {
         error_log("Add country form submitted");
         error_log(print_r($_POST,true));
        $nom = $_POST["nom"];
        $population = $_POST["population"];
        $langues = $_POST["langues"];
         $cities = isset($_POST["cities"]) ? json_decode($_POST["cities"], true) : [];

        if (empty($nom)) {
            $feedbackMessage = "Country name is required.";
        } else {
            $continentName = 'Afrique';
            $stmt = $conn->prepare("SELECT id_continent FROM continent WHERE nom = ?");
            $stmt->bind_param("s", $continentName);
            $stmt->execute();
             $result = $stmt->get_result();
            if ($row = $result->fetch_assoc()) {
                 $id_continent = $row['id_continent'];
                 $stmt->close();
                 $sql = "INSERT INTO pays (nom, population, id_continent, langues) VALUES (?, ?, ?, ?)";
                 error_log("SQL for adding the country:" . $sql);
               $stmt = $conn->prepare($sql);
                 if($stmt === false){
                    $feedbackMessage = "Error in SQL statement: " . $conn->error;
                    error_log("Error with the prepare: " .  $conn->error);
                } else {
                    $stmt->bind_param("siss", $nom, $population, $id_continent, $langues);
                    if ($stmt->execute()) {
                        $country_id = $conn->insert_id;
                          error_log("country was added successfully, inserted id: " . $country_id);
                         $stmt->close();
                           foreach ($cities as $city) {
                             $cityNom = $city["nom"];
                             $cityDescription = $city["description"];
                            $cityType = $city["type"];
                                 $sqlCity = "INSERT INTO ville (nom, description, type, id_pays) VALUES (?, ?, ?, ?)";
                                  error_log("SQL for adding city:" . $sqlCity);
                           $stmtCity = $conn->prepare($sqlCity);
                            if($stmtCity === false){
                                $feedbackMessage = "Error in SQL statement: " . $conn->error;
                                 error_log("Error with the prepare: " .  $conn->error);
                                break;
                           } else {
                                $stmtCity->bind_param("sssi", $cityNom, $cityDescription, $cityType, $country_id);
                                 if (!$stmtCity->execute()) {
                                    $feedbackMessage = "Error adding city: " . $stmtCity->error;
                                     error_log("Error with inserting cities: " .  $stmtCity->error);
                                    break;
                                 }
                                 $stmtCity->close();
                            }
                        }
                         if(empty($feedbackMessage)){
                            $feedbackMessage = "Country and cities added successfully.";
                            error_log("Country and cities added successfully.");
                        }
                    } else {
                       $feedbackMessage = "Error adding country: " . $stmt->error;
                         error_log("Error with inserting the country: " . $stmt->error);
                   }
                }
            } else {
                $feedbackMessage = "Continent 'Africa' not found in the database.";
                $stmt->close();
                error_log("Continent Africa not found");
            }
        }

    }
    if(isset($_POST['edit_country'])){
        $idPays = $_POST["id_pays"];
         $nom = $_POST["nom"];
       $population = $_POST["population"];
       $langues = $_POST["langues"];
         $sql = "UPDATE pays SET nom = ?, population = ?, langues = ? WHERE id_pays = ?";
           error_log("SQL for updating the country:" . $sql);
       $stmt = $conn->prepare($sql);
        if($stmt === false){
             $feedbackMessage = "Error in SQL statement: " . $conn->error;
             error_log("Error with prepare: " .  $conn->error);
         }else{
            $stmt->bind_param("sssi", $nom, $population, $langues, $idPays);
             if ($stmt->execute()) {
                   $feedbackMessage = "Country edited successfully.";
                    error_log("country updated successfully");
                } else {
                   $feedbackMessage = "Error editing country: " . $stmt->error;
                     error_log("Error with updating: " .  $stmt->error);
            }
          $stmt->close();
        }
    }
      if (isset($_POST['edit_city'])) {
          $idVille = $_POST["id_ville"];
         $cityNom = $_POST["city_nom"];
          $cityDescription = $_POST["city_description"];
         $cityType = $_POST["city_type"];
           $sql = "UPDATE ville SET nom = ?, description = ?, type = ? WHERE id_ville = ?";
            error_log("SQL for updating the city:" . $sql);
          $stmt = $conn->prepare($sql);
           if($stmt === false){
                $feedbackMessage = "Error in SQL statement: " . $conn->error;
                 error_log("Error with the prepare: " .  $conn->error);
           } else {
                $stmt->bind_param("sssi", $cityNom, $cityDescription, $cityType, $idVille);
               if ($stmt->execute()) {
                     $feedbackMessage = "City updated successfully.";
                    error_log("city updated successfully");
                } else {
                     $feedbackMessage = "Error updating city: " . $stmt->error;
                     error_log("Error with updating: " .  $stmt->error);
               }
           $stmt->close();
         }
     }
     if (isset($_POST['add_city_to_country'])) { // New form handling for adding cities
         $country_id = $_POST['country_id'];
        $city_nom = $_POST['city_nom'];
       $city_description = $_POST['city_description'];
         $city_type = $_POST['city_type'];

          $sqlCity = "INSERT INTO ville (nom, description, type, id_pays) VALUES (?, ?, ?, ?)";
            $stmtCity = $conn->prepare($sqlCity);
           if ($stmtCity === false) {
                $feedbackMessage = "Error in SQL statement: " . $conn->error;
                 error_log("Error with the prepare: " . $conn->error);
          } else {
                $stmtCity->bind_param("sssi", $city_nom, $city_description, $city_type, $country_id);
              if (!$stmtCity->execute()) {
                    $feedbackMessage = "Error adding city: " . $stmtCity->error;
                    error_log("Error with inserting cities: " . $stmtCity->error);
               } else {
                   $feedbackMessage = "City added successfully.";
                     error_log("City added successfully to country ID: " . $country_id);
                }
             $stmtCity->close();
            }
      }
      if(isset($_POST['delete_country'])){
          $idPays = $_POST["id_pays"];
          $sql = "DELETE FROM ville WHERE id_pays = ?";
            error_log("SQL for deleting the city:" . $sql);
        $stmt = $conn->prepare($sql);
         if($stmt === false){
                $feedbackMessage = "Error in SQL statement: " . $conn->error;
                   error_log("Error with the prepare: " .  $conn->error);
           } else {
                $stmt->bind_param("i", $idPays);
                 if($stmt->execute()){
                    $stmt->close();
                     $sqlCountry = "DELETE FROM pays WHERE id_pays = ?";
                     error_log("SQL for deleting the country:" . $sqlCountry);
                     $stmtCountry = $conn->prepare($sqlCountry);
                      if($stmtCountry === false){
                            $feedbackMessage = "Error in SQL statement: " . $conn->error;
                             error_log("Error with the prepare: " .  $conn->error);
                     }else {
                           $stmtCountry->bind_param("i", $idPays);
                             if($stmtCountry->execute()){
                                  $feedbackMessage = "Country and associated cities deleted.";
                                   error_log("country and cities deleted");
                            }else{
                                  $feedbackMessage = "Error deleting country: " . $stmtCountry->error;
                                    error_log("Error with updating country " . $stmtCountry->error);
                           }
                           $stmtCountry->close();
                      }

                 } else {
                    $feedbackMessage = "Error deleting cities: " . $stmt->error;
                     error_log("Error with updating city:" . $stmt->error);
                }
           }
      }
        if(isset($_POST['delete_city'])){
           $idVille = $_POST["id_ville"];
            $sql = "DELETE FROM ville WHERE id_ville = ?";
              error_log("SQL for deleting the city:" . $sql);
          $stmt = $conn->prepare($sql);
          if($stmt === false){
               $feedbackMessage = "Error in SQL statement: " . $conn->error;
                error_log("Error with the prepare: " .  $conn->error);
        } else{
           $stmt->bind_param("i", $idVille);
           if($stmt->execute()){
               $feedbackMessage = "City deleted successfully.";
               error_log("city deleted successfully");
           }else{
                  $feedbackMessage = "Error deleting city: " . $stmt->error;
                    error_log("Error with updating city:" . $stmt->error);
           }
         $stmt->close();
         }
    }
}

$sql = "SELECT * FROM pays ORDER BY nom";
$offset = ($page - 1) * $perPage;
$sql .= " LIMIT $offset, $perPage";
$result = $conn->query($sql);

$countryMap = [
    'Algeria' => 'DZ',
    'Angola' => 'AO',
    'Benin' => 'BJ',
    'Botswana' => 'BW',
    'Burkina Faso' => 'BF',
    'Burundi' => 'BI',
    'Cabo Verde' => 'CV',
    'Cameroon' => 'CM',
    'Central African Republic' => 'CF',
    'Chad' => 'TD',
    'Comoros' => 'KM',
    'DR Congo' => 'CD',
    'Republic of the Congo' => 'CG',
    'Djibouti' => 'DJ',
    'Egypt' => 'EG',
    'Equatorial Guinea' => 'GQ',
    'Eritrea' => 'ER',
    'Eswatini' => 'SZ',
    'Ethiopia' => 'ET',
    'Gabon' => 'GA',
    'Gambia' => 'GM',
    'Ghana' => 'GH',
    'Guinea' => 'GN',
    'Guinea-Bissau' => 'GW',
    'Ivory Coast' => 'CI',
    'Kenya' => 'KE',
    'Lesotho' => 'LS',
    'Liberia' => 'LR',
    'Libya' => 'LY',
    'Madagascar' => 'MG',
    'Malawi' => 'MW',
    'Mali' => 'ML',
    'Mauritania' => 'MR',
    'Mauritius' => 'MU',
    'Morocco' => 'MA',
    'Mozambique' => 'MZ',
    'Namibia' => 'NA',
    'Niger' => 'NE',
    'Nigeria' => 'NG',
    'Rwanda' => 'RW',
    'Sao Tome and Principe' => 'ST',
    'Senegal' => 'SN',
    'Seychelles' => 'SC',
     'Sierra Leone' => 'SL',
    'Somalia' => 'SO',
    'South Africa' => 'ZA',
    'South Sudan' => 'SS',
    'Sudan' => 'SD',
    'Tanzania' => 'TZ',
    'Togo' => 'TG',
    'Tunisia' => 'TN',
    'Uganda' => 'UG',
    'Zambia' => 'ZM',
    'Zimbabwe' => 'ZW',
];

$totalCountries = $conn->query("SELECT COUNT(*) AS count FROM pays")->fetch_assoc()['count'];
$totalPages = ceil($totalCountries / $perPage);

$style = 'flat';
$size = '64';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>African Flags</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-100 font-sans">
    <button id="addCountryBtn" class="add-country-button absolute top-5 left-5 bg-green-500 hover:bg-green-700 text-white font-bold py-2 px-4 rounded">Add Country</button>
    <div id="addCountryModal" class="modal fixed hidden z-10 inset-0 overflow-y-auto bg-gray-900 bg-opacity-50">
        <div class="modal-content bg-white mx-auto my-20 p-6 border border-gray-200 rounded-md w-4/5 max-w-lg">
            <span class="close text-gray-600 float-right text-2xl font-bold cursor-pointer">×</span>
            <h2 class="text-2xl font-bold mb-4">Add New Country</h2>
              <?php if (!empty($feedbackMessage)) {
                   echo "<p class='text-red-600 mt-2 mb-4'>$feedbackMessage</p>";
                }?>
            <form action="" method="post">
               <div class="mb-4">
                 <label for="nom" class="block text-gray-700 text-sm font-bold mb-2">Country Name:</label>
                  <input type="text" id="nom" name="nom" required class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline">
               </div>
                <div class="mb-4">
                    <label for="population" class="block text-gray-700 text-sm font-bold mb-2">Population:</label>
                     <input type="number" id="population" name="population" class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline">
               </div>
               <div class="mb-4">
                  <label for="langues" class="block text-gray-700 text-sm font-bold mb-2">Languages (comma-separated):</label>
                  <input type="text" id="langues" name="langues" class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline">
               </div>
                <div id="cities-container">
                </div>
                <input type="hidden" id="citiesInput" name="cities" >
               <div class="mt-6">
                    <input type="submit" name="add_country" value="Add Country" class="bg-green-500 hover:bg-green-700 text-white font-bold py-2 px-4 rounded focus:outline-none focus:shadow-outline cursor-pointer">
               </div>
            </form>
        </div>
    </div>
   <div class="mt-16">
        <?php
            if ($result->num_rows > 0) {
              while ($country = $result->fetch_assoc()) {
                $countryName = $country['nom'];
                if (isset($countryMap[$countryName])) {
                    $code = $countryMap[$countryName];
                    $flagUrl = "https://flagsapi.com/$code/$style/$size.png";
                   echo '<div class="bg-white rounded-md shadow p-4 w-full mb-8">';
                     echo '<div class="flex items-center space-x-4 mb-4">';
                       echo '<img src="' . $flagUrl . '" alt="' . $countryName . ' flag" class="w-20 h-20 object-contain cursor-pointer">';
                      echo '<h2 class="text-xl font-bold">'. $countryName .'</h2>';
                           echo '<button class="toggle-country-info ml-auto bg-gray-200 hover:bg-gray-300 text-gray-700 font-bold py-1 px-2 rounded" data-country-id="' . $country['id_pays'] . '">Show Details</button>';
                        echo '</div>';
                         echo '<div id="country-info-' . $country['id_pays'] . '" class="country-info hidden">';
                         echo "<form method='post' class='mt-4'>";
                            echo "<input type='hidden' name='id_pays' value='" . $country['id_pays'] . "'/>";
                              echo '<div class="mb-2">';
                                    echo  '<label class="block text-gray-700 text-sm font-bold mb-1">Country Name:</label>';
                                    echo  '<input type="text" name="nom" value="' . $country['nom'] . '" class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline">';
                              echo '</div>';
                                echo '<div class="mb-2">';
                                     echo  '<label class="block text-gray-700 text-sm font-bold mb-1">Population:</label>';
                                      echo  '<input type="text" name="population" value="' . $country['population'] . '" class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline">';
                                  echo '</div>';
                                    echo '<div class="mb-2">';
                                       echo '<label class="block text-gray-700 text-sm font-bold mb-1">Languages:</label>';
                                         echo '<input type="text" name="langues" value="' . $country['langues'] . '"  class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline">';
                                   echo '</div>';
                               echo '<div class="flex justify-end">';
                                    echo '<button type="submit" name="edit_country" class="bg-blue-500 hover:bg-blue-700 text-white font-bold py-2 px-4 rounded">Modify</button>';
                                     echo '<button type="submit" name="delete_country" class="bg-red-500 hover:bg-red-700 text-white font-bold py-2 px-4 rounded ml-2">Delete</button>';
                              echo '</div>';
                         echo "</form>";
                      echo '<div class="mt-4">';
                           $sqlCities = "SELECT * FROM ville WHERE id_pays = ?";
                             $stmtCities = $conn->prepare($sqlCities);
                             $stmtCities->bind_param("i", $country['id_pays']);
                           $stmtCities->execute();
                             $resultCities = $stmtCities->get_result();
                         if($resultCities->num_rows > 0){
                                 while ($city = $resultCities->fetch_assoc()) {
                                    echo  '<div class="city-container mt-4 border border-gray-300 rounded-md p-3">';
                                          echo "<form method='post'>";
                                            echo "<input type='hidden' name='id_ville' value='" . $city['id_ville'] . "'/>";
                                             echo '<div class="mb-2">';
                                                  echo  '<label class="block text-gray-700 text-sm font-bold mb-1">City Name:</label>';
                                                  echo  '<input type="text" name="city_nom" value="' . $city['nom'] . '"  class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline">';
                                          echo '</div>';
                                             echo '<div class="mb-2">';
                                                  echo  '<label class="block text-gray-700 text-sm font-bold mb-1">Description:</label>';
                                                 echo  '<textarea name="city_description" class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline">' . $city['description'] . '</textarea>';
                                              echo '</div>';
                                             echo '<div class="mb-2">';
                                                  echo   '<label class="block text-gray-700 text-sm font-bold mb-1">Type:</label>';
                                                   echo  '<select  name="city_type"  class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline">';
                                                       echo  '<option value="capitale" '. ($city['type'] === 'capitale' ? 'selected' : '') .'>Capital</option>';
                                                        echo '<option value="autre" ' .  ($city['type'] === 'autre' ? 'selected' : '') . '>Other</option>';
                                                   echo  '</select>';
                                            echo '</div>';
                                          echo '<div class="flex justify-between">';
                                              echo  '<button type="submit" name="edit_city" class="bg-blue-500 hover:bg-blue-700 text-white font-bold py-1 px-2 rounded">Modify</button>';
                                                echo '<button type="submit" name="delete_city" class="bg-red-500 hover:bg-red-700 text-white font-bold py-1 px-2 rounded ml-2">Delete</button>';
                                            echo '</div>';
                                        echo "</form>";
                                    echo  '</div>';

                                 }
                           }
                         $stmtCities->close();
                        echo '</div>';
                        echo '<div class="mt-4">';
                          echo '<form method="post">';
                            echo '<input type="hidden" name="country_id" value="' . $country['id_pays'] . '">';
                             echo '<input type="text" name="city_nom" placeholder="City Name" required class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline mb-2">';
                            echo '<textarea name="city_description" placeholder="Description" class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline mb-2"></textarea>';
                            echo '<select name="city_type" class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline mb-2">';
                            echo '<option value="capitale">Capital</option>';
                            echo '<option value="autre">Other</option>';
                             echo '</select>';
                            echo '<button type="submit" name="add_city_to_country" class="bg-blue-500 hover:bg-blue-700 text-white font-bold py-2 px-4 rounded">Add City</button>';
                             echo  '</form>';
                          echo  '</div>';
                    echo '</div>';
                   echo '</div>';
                    }
            }
          }
        ?>
    </div>
    <div class="pagination flex justify-center mt-8">
        <?php if ($page > 1): ?>
            <a href="?page=<?= $page - 1 ?>" class="px-4 py-2 mx-1 bg-gray-200 rounded hover:bg-gray-300">« Previous</a>
        <?php endif; ?>
         <?php for ($i = 1; $i <= $totalPages; $i++): ?>
                <a href="?page=<?= $i ?>" class="px-4 py-2 mx-1 <?php echo ($page == $i) ? 'bg-blue-500 text-white' : 'bg-gray-200 hover:bg-gray-300'?>  rounded"><?= $i ?></a>
          <?php endfor; ?>
        <?php if ($page < $totalPages): ?>
            <a href="?page=<?= $page + 1 ?>" class="px-4 py-2 mx-1 bg-gray-200 rounded hover:bg-gray-300">Next »</a>
        <?php endif; ?>
    </div>
   <script>
   document.addEventListener('DOMContentLoaded', function() {
    var modal = document.getElementById("addCountryModal");
    var btn = document.getElementById("addCountryBtn");
    var span = document.getElementsByClassName("close")[0];
    var citiesContainer = document.getElementById("cities-container");
    var citiesInput = document.getElementById("citiesInput");
    var cities = [];

    btn.onclick = function() {
        modal.style.display = "block";
    }

    span.onclick = function() {
        modal.style.display = "none";
    }

    window.onclick = function(event) {
        if (event.target == modal) {
            modal.style.display = "none";
        }
    }

    function attachToggleListeners() {
        document.querySelectorAll('.toggle-country-info').forEach(function(button) {
            button.addEventListener('click', function() {
                var countryId = this.getAttribute('data-country-id');
                var countryInfoDiv = document.getElementById('country-info-' + countryId);
                countryInfoDiv.classList.toggle('hidden');

                var isExpanded = countryInfoDiv.classList.contains('hidden');
                localStorage.setItem('country-expanded-' + countryId, !isExpanded);
            });

            var countryId = button.getAttribute('data-country-id');
            var countryInfoDiv = document.getElementById('country-info-' + countryId);
            var isExpanded = localStorage.getItem('country-expanded-' + countryId) === 'true';
            if (isExpanded) {
                countryInfoDiv.classList.remove('hidden');
            }
        });
    }

    attachToggleListeners();

   document.querySelector("form")?.addEventListener("submit", function(event) {
        cities = [];
          document.querySelectorAll(".city-container").forEach(function(cityContainer) {
                  const nom = cityContainer.querySelector("input[name='city_nom']").value;
                const description = cityContainer.querySelector("textarea[name='city_description']").value;
                 const type = cityContainer.querySelector("select[name='city_type']").value;
            cities.push({ nom: nom, description: description, type: type });
        });
          citiesInput.value = JSON.stringify(cities);

    });
});
    </script>
</body>
</html>
<?php $conn->close(); ?>