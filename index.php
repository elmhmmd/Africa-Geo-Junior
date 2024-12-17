<?php
// Database connection settings
$servername = "localhost";
$username = "root";
$password = "";
$dbname = "africa_geo_junior";

// Global variables for feedback and pagination
$feedbackMessage = "";
$selectedCountry = null;
$perPage = 5;
$page = isset($_GET['page']) && is_numeric($_GET['page']) ? (int)$_GET['page'] : 1;
$conn = null; // Database connection variable
$nomError = "";

// Function to connect to the database
function connectDB() {
    global $servername, $username, $password, $dbname, $conn;
    $conn = new mysqli($servername, $username, $password, $dbname);
    if ($conn->connect_error) {
        die("Connection failed: " . $conn->connect_error);
    }
    $conn->set_charset("utf8");
}

// Function to close the database connection
function closeDB() {
    global $conn;
    if ($conn) {
        $conn->close();
    }
}


// Function to add a new country
function addCountry() {
  global $conn, $feedbackMessage, $nomError;
   if (isset($_POST['add_country'])) {
        $nom = $_POST["nom"];
        $population = $_POST["population"];
        $langues = $_POST["langues"];
        $cities = isset($_POST["cities"]) ? json_decode($_POST["cities"], true) : [];


         // Check if country already exists (case-insensitive)
        $checkSql = "SELECT COUNT(*) FROM pays WHERE LOWER(nom) = LOWER(?)";
        $checkStmt = $conn->prepare($checkSql);
        $checkStmt->bind_param("s", $nom);
        $checkStmt->execute();
        $checkStmt->bind_result($count);
        $checkStmt->fetch();
        $checkStmt->close();

          if ($count > 0) {
            $nomError = "Country already exists.";
        } else if (empty($nom)) {
            $nomError = "Country name is required.";
        } else {
           $continentName = 'Afrique';
            $stmt = $conn->prepare("SELECT id_continent FROM continent WHERE nom = ?");
            $stmt->bind_param("s", $continentName);
            $stmt->execute();
             $result = $stmt->get_result();
              if ($row = $result->fetch_assoc()) {
                $id_continent = $row['id_continent'];
                $stmt->close();

                  // Begin transaction for atomicity
                $conn->begin_transaction();
                $sql = "INSERT INTO pays (nom, population, id_continent, langues) VALUES (?, ?, ?, ?)";
                $stmt = $conn->prepare($sql);
                $stmt->bind_param("siss", $nom, $population, $id_continent, $langues);
                if ($stmt->execute()) {
                    $country_id = $conn->insert_id;
                    $stmt->close();

                    // Insert cities ONLY if they are provided
                    $cityInsertSuccess = true;
                    if (!empty($cities)) {
                        foreach ($cities as $city) {
                            $cityNom = $city["nom"];
                            $cityDescription = $city["description"];
                            $cityType = $city["type"];
                            $sqlCity = "INSERT INTO ville (nom, description, type, id_pays) VALUES (?, ?, ?, ?)";
                            $stmtCity = $conn->prepare($sqlCity);
                            $stmtCity->bind_param("sssi", $cityNom, $cityDescription, $cityType, $country_id);
                            if (!$stmtCity->execute()) {
                                $cityInsertSuccess = false;
                                $feedbackMessage = "Error adding city: " . $stmtCity->error;
                                break;
                            }
                            $stmtCity->close();
                        }
                    }

                     if ($cityInsertSuccess && empty($nomError)) {
                         // Commit transaction if everything is successful
                        $conn->commit();
                         $feedbackMessage = "Country and cities added successfully.";
                     } else {
                        // Rollback if there's an error
                        $conn->rollback();
                        if (empty($feedbackMessage)) {
                             $feedbackMessage = "Error adding country or cities.";
                          }
                        }
                    } else {
                      $conn->rollback();
                      $feedbackMessage = "Error adding country: " . $stmt->error;
                   }
                } else {
                    $feedbackMessage = "Continent 'Africa' not found in the database.";
                }
            }
        }
}

// Function to edit an existing country
function editCountry() {
    global $conn, $feedbackMessage;
    if (isset($_POST['edit_country'])) {
        $idPays = $_POST["id_pays"];
        $nom = $_POST["nom"];
        $population = $_POST["population"];
        $langues = $_POST["langues"];

        // Check for duplicate country name (case-insensitive, excluding the current country)
        $checkSql = "SELECT COUNT(*) FROM pays WHERE LOWER(nom) = LOWER(?) AND id_pays != ?";
        $checkStmt = $conn->prepare($checkSql);
        $checkStmt->bind_param("si", $nom, $idPays);
        $checkStmt->execute();
        $checkStmt->bind_result($count);
        $checkStmt->fetch();
        $checkStmt->close();

        if ($count > 0) {
            $feedbackMessage = "Country name already exists.";
        } else {
             $sql = "UPDATE pays SET nom = ?, population = ?, langues = ? WHERE id_pays = ?";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("sssi", $nom, $population, $langues, $idPays);
              if ($stmt->execute()) {
                 $feedbackMessage = "Country edited successfully.";
            } else {
                $feedbackMessage = "Error editing country: " . $stmt->error;
             }
            $stmt->close();
       }
    }
}

// Function to edit an existing city
function editCity() {
    global $conn, $feedbackMessage;
    if (isset($_POST['edit_city'])) {
        $idVille = $_POST["id_ville"];
        $cityNom = $_POST["city_nom"];
        $cityDescription = $_POST["city_description"];
        $cityType = $_POST["city_type"];
        $sql = "UPDATE ville SET nom = ?, description = ?, type = ? WHERE id_ville = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("sssi", $cityNom, $cityDescription, $cityType, $idVille);
        if ($stmt->execute()) {
            $feedbackMessage = "City updated successfully.";
        } else {
            $feedbackMessage = "Error updating city: " . $stmt->error;
        }
        $stmt->close();
    }
}


// Function to add a new city to a country
function addCityToCountry() {
    global $conn, $feedbackMessage;
    if (isset($_POST['add_city_to_country'])) {
        $country_id = $_POST['country_id'];
        $city_nom = $_POST['city_nom'];
        $city_description = $_POST['city_description'];
        $city_type = $_POST['city_type'];

        $sqlCity = "INSERT INTO ville (nom, description, type, id_pays) VALUES (?, ?, ?, ?)";
        $stmtCity = $conn->prepare($sqlCity);
        $stmtCity->bind_param("sssi", $city_nom, $city_description, $city_type, $country_id);
        if ($stmtCity->execute()) {
            $feedbackMessage = "City added successfully.";
        } else {
            $feedbackMessage = "Error adding city: " . $stmtCity->error;
        }
        $stmtCity->close();
    }
}

// Function to delete a country and its associated cities
function deleteCountry() {
   global $conn, $feedbackMessage;
   if (isset($_POST['delete_country'])) {
         $idPays = $_POST["id_pays"];

        // Use transactions to ensure atomicity
        $conn->begin_transaction();

        // Delete cities first (due to foreign key constraint)
        $sqlCities = "DELETE FROM ville WHERE id_pays = ?";
        $stmtCities = $conn->prepare($sqlCities);
        $stmtCities->bind_param("i", $idPays);

        if ($stmtCities->execute()) {
            $stmtCities->close();

             // Then delete the country
            $sqlCountry = "DELETE FROM pays WHERE id_pays = ?";
            $stmtCountry = $conn->prepare($sqlCountry);
            $stmtCountry->bind_param("i", $idPays);

              if ($stmtCountry->execute()) {
                $conn->commit(); // Commit if both deletes are successful
                $feedbackMessage = "Country and associated cities deleted.";
              } else {
                $conn->rollback(); // Rollback if there's an error deleting the country
                 $feedbackMessage = "Error deleting country: " . $stmtCountry->error;
              }
             $stmtCountry->close();
        } else {
             $conn->rollback(); // Rollback if there's an error deleting cities
            $feedbackMessage = "Error deleting cities: " . $stmtCities->error;
        }
    }
}

// Function to delete a city
function deleteCity() {
    global $conn, $feedbackMessage;
    if (isset($_POST['delete_city'])) {
        $idVille = $_POST["id_ville"];
        $sql = "DELETE FROM ville WHERE id_ville = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("i", $idVille);
        if ($stmt->execute()) {
            $feedbackMessage = "City deleted successfully.";
        } else {
            $feedbackMessage = "Error deleting city: " . $stmt->error;
        }
        $stmt->close();
    }
}

// Function to fetch countries with pagination
function fetchCountries($perPage, $page) {
    global $conn;
    $sql = "SELECT * FROM pays ORDER BY nom";
    $offset = ($page - 1) * $perPage;
    $sql .= " LIMIT $offset, $perPage";
    $result = $conn->query($sql);
    return $result;
}

// Function to calculate total pages for pagination
function calculateTotalPages($perPage) {
    global $conn;
    $totalCountries = $conn->query("SELECT COUNT(*) AS count FROM pays")->fetch_assoc()['count'];
    return ceil($totalCountries / $perPage);
}


// Function to handle form submissions
function handleFormSubmission() {
  connectDB();
   addCountry();
    editCountry();
    editCity();
    addCityToCountry();
    deleteCountry();
    deleteCity();

}

// Country code mapping array
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

$style = 'flat';
$size = '64';

// Handle form submissions first before rendering the page
handleFormSubmission();

// Connect to the database only when it's needed for fetching data
if(!isset($_POST['add_country'])){
  connectDB();
}

$result = fetchCountries($perPage, $page);
$totalPages = calculateTotalPages($perPage);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>African Flags</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <style>
        .input-container {
            margin-bottom: 0.75rem;
        }
    </style>
</head>
<body class="bg-gray-100 font-sans">
    <button id="addCountryBtn" class="add-country-button absolute top-5 left-5 bg-green-500 hover:bg-green-700 text-white font-bold py-2 px-4 rounded mb-6">Add Country</button>
    <div id="addCountryModal" class="modal fixed hidden z-10 inset-0 overflow-y-auto bg-gray-900 bg-opacity-50">
        <div class="modal-content bg-white mx-auto my-20 p-6 border border-gray-200 rounded-md w-4/5 max-w-lg">
            <span class="close text-gray-600 float-right text-2xl font-bold cursor-pointer">×</span>
            <h2 class="text-2xl font-bold mb-4">Add New Country</h2>
              <?php if (!empty($feedbackMessage) && !isset($nomError) ) {
                   echo "<p class='text-green-600 mt-2 mb-4'>$feedbackMessage</p>";
                }?>
            <form id="addCountryForm" action="" method="post"  class="flex flex-col">
                <div class="input-container">
                  <label for="nom" class="block text-gray-700 text-sm font-bold mb-2">Country Name:</label>
                   <input type="text" id="nom" name="nom" required class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline">
                    <?php if (isset($nomError)) { echo "<p class='text-red-600 mt-2'>$nomError</p>"; } ?>
                </div>
                <div class="input-container">
                    <label for="population" class="block text-gray-700 text-sm font-bold mb-2">Population:</label>
                     <input type="number" id="population" name="population" class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline">
                </div>
               <div class="input-container">
                  <label for="langues" class="block text-gray-700 text-sm font-bold mb-2">Languages (comma-separated):</label>
                  <input type="text" id="langues" name="langues" class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline">
               </div>
                 <div id="new-cities-container">

                  </div>
                  <button type="button" id="addCityBtn" class="bg-blue-500 hover:bg-blue-700 text-white font-bold py-2 px-4 rounded mt-4">Add City</button>
                <input type="hidden" id="citiesInput" name="cities" >
               <div class="mt-6">
                    <input type="submit" name="add_country" value="Add Country" class="bg-green-500 hover:bg-green-700 text-white font-bold py-2 px-4 rounded focus:outline-none focus:shadow-outline cursor-pointer">
               </div>
            </form>
        </div>
    </div>
   <div class="mt-16  mx-auto w-4/5">
        <?php
            if ($result && $result->num_rows > 0) {
              while ($country = $result->fetch_assoc()) {
                $countryName = $country['nom'];
                if (isset($countryMap[$countryName])) {
                    $code = $countryMap[$countryName];
                    $flagUrl = "https://flagsapi.com/$code/$style/$size.png";
                   echo '<div class="bg-white rounded-md shadow p-4 w-full mb-8  border">';
                     echo '<div class="flex items-center space-x-4 mb-4">';
                       echo '<img src="' . $flagUrl . '" alt="' . $countryName . ' flag" class="w-20 h-20 object-contain cursor-pointer">';
                      echo '<h2 class="text-xl font-bold">'. $countryName .'</h2>';
                           echo '<button class="toggle-country-info ml-auto bg-gray-200 hover:bg-gray-300 text-gray-700 font-bold py-1 px-2 rounded" data-country-id="' . $country['id_pays'] . '">Show Details</button>';
                        echo '</div>';
                         echo '<div id="country-info-' . $country['id_pays'] . '" class="country-info hidden">';
                         echo "<form method='post' class='mt-4 flex flex-col'>";
                            echo "<input type='hidden' name='id_pays' value='" . $country['id_pays'] . "'/>";
                            echo '<div class="input-container">';
                                    echo  '<label class="block text-gray-700 text-sm font-bold mb-1">Country Name:</label>';
                                    echo  '<input type="text" name="nom" value="' . $country['nom'] . '" class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline">';
                              echo '</div>';
                                echo '<div class="input-container">';
                                     echo  '<label class="block text-gray-700 text-sm font-bold mb-1">Population:</label>';
                                      echo  '<input type="text" name="population" value="' . $country['population'] . '" class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline">';
                                  echo '</div>';
                                    echo '<div class="input-container">';
                                       echo '<label class="block text-gray-700 text-sm font-bold mb-1">Languages:</label>';
                                         echo '<input type="text" name="langues" value="' . $country['langues'] . '"  class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline">';
                                   echo '</div>';
                               echo '<div class="flex justify-end mt-4">';
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
                                             echo '<div class="input-container">';
                                                  echo  '<label class="block text-gray-700 text-sm font-bold mb-1">City Name:</label>';
                                                  echo  '<input type="text" name="city_nom" value="' . $city['nom'] . '"  class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline">';
                                          echo '</div>';
                                             echo '<div class="input-container">';
                                                  echo  '<label class="block text-gray-700 text-sm font-bold mb-1">Description:</label>';
                                                 echo  '<textarea name="city_description" class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline">' . $city['description'] . '</textarea>';
                                              echo '</div>';
                                             echo '<div class="input-container">';
                                                  echo   '<label class="block text-gray-700 text-sm font-bold mb-1">Type:</label>';
                                                   echo  '<select  name="city_type"  class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline">';
                                                       echo  '<option value="capitale" '. ($city['type'] === 'capitale' ? 'selected' : '') .'>Capital</option>';
                                                        echo '<option value="autre" ' . ($city['type'] === 'autre' ? 'selected' : '') . '>Other</option>';
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
                          echo '<form method="post" class="flex flex-col">';
                            echo '<input type="hidden" name="country_id" value="' . $country['id_pays'] . '">';
                             echo '<div class="input-container">';
                               echo '<input type="text" name="city_nom" placeholder="City Name" required class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline mb-2">';
                              echo '</div>';
                              echo '<div class="input-container">';
                                echo '<textarea name="city_description" placeholder="Description" class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline mb-2"></textarea>';
                              echo '</div>';
                              echo '<div class="input-container">';
                                echo '<select name="city_type" class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline mb-2">';
                                echo '<option value="capitale">Capital</option>';
                                echo '<option value="autre">Other</option>';
                                 echo '</select>';
                               echo '</div>';
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
    <div class="pagination flex justify-center mt-8 mb-8">
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
        var newCitiesContainer = document.getElementById("new-cities-container");
        var citiesInput = document.getElementById("citiesInput");
        var addCityBtn = document.getElementById('addCityBtn');
        var cities = [];
        let cityCount = 0;

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
        addCityBtn.addEventListener('click', function () {
            const cityDiv = document.createElement('div');
            cityDiv.classList.add('city-container', 'mb-4', 'p-3', 'border', 'border-gray-300', 'rounded-md');
            cityDiv.innerHTML = `
                <div class="input-container">
                    <input type="text" name="city_nom_${cityCount}" placeholder="City Name" required class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline mb-2">
                </div>
                <div class="input-container">
                  <textarea name="city_description_${cityCount}" placeholder="Description" class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline mb-2"></textarea>
                 </div>
                <div class="input-container">
                    <select name="city_type_${cityCount}" class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline mb-2">
                        <option value="capitale">Capital</option>
                        <option value="autre">Other</option>
                    </select>
                </div>
                <button type="button" class="remove-city bg-red-500 hover:bg-red-700 text-white font-bold py-1 px-2 rounded mt-2">Remove City</button>
             `;

            newCitiesContainer.appendChild(cityDiv);

            cityCount++;

            cityDiv.querySelector('.remove-city').addEventListener('click', function () {
              cityDiv.remove();
           });

        });

      document.getElementById("addCountryForm")?.addEventListener("submit", function(event) {
            cities = [];
          newCitiesContainer.querySelectorAll(".city-container").forEach(function(cityContainer,index) {
              const nom = cityContainer.querySelector(`input[name='city_nom_${index}']`).value;
             const description = cityContainer.querySelector(`textarea[name='city_description_${index}']`).value;
                const type = cityContainer.querySelector(`select[name='city_type_${index}']`).value;
             cities.push({ nom: nom, description: description, type: type });
            });
            citiesInput.value = JSON.stringify(cities);
        });
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


    });
    </script>
</body>
</html>
<?php closeDB(); ?>
