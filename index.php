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

if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['add_country'])) {
    $nom = $_POST["nom"];
    $population = $_POST["population"];
    $langues = $_POST["langues"];
    $cities = $_POST["cities"] ?? [];

    if (empty($nom)) {
        $feedbackMessage = "Country name is required.";
    } else {
        $continentName = 'Africa';
        $stmt = $conn->prepare("SELECT id_continent FROM continent WHERE nom = ?");
        $stmt->bind_param("s", $continentName);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($row = $result->fetch_assoc()) {
            $id_continent = $row['id_continent'];
            $stmt->close();

            $sql = "INSERT INTO pays (nom, population, id_continent, langues) VALUES (?, ?, ?, ?)";
            $stmt = $conn->prepare($sql);

            if($stmt === false){
                 $feedbackMessage = "Error in SQL statement: " . $conn->error;
           } else {
               $stmt->bind_param("siss", $nom, $population, $id_continent, $langues);

                if ($stmt->execute()) {
                     $country_id = $conn->insert_id;
                      $stmt->close();

                   foreach ($cities as $city) {
                       $cityNom = $city["nom"];
                       $cityDescription = $city["description"];
                       $cityType = $city["type"];
                        $sqlCity = "INSERT INTO ville (nom, description, type, id_pays) VALUES (?, ?, ?, ?)";
                        $stmtCity = $conn->prepare($sqlCity);

                      if($stmtCity === false){
                            $feedbackMessage = "Error in SQL statement: " . $conn->error;
                            break;
                       } else {
                           $stmtCity->bind_param("sssi", $cityNom, $cityDescription, $cityType, $country_id);
                           if (!$stmtCity->execute()) {
                               $feedbackMessage = "Error adding city: " . $stmtCity->error;
                               break;
                           }
                          $stmtCity->close();
                       }

                   }
                       if(empty($feedbackMessage)){
                            $feedbackMessage = "Country and cities added successfully.";
                       }
                } else {
                    $feedbackMessage = "Error adding country: " . $stmt->error;
                }
           }

        } else {
            $feedbackMessage = "Continent 'Africa' not found in the database.";
            $stmt->close();
        }
    }
}
if (isset($_GET['country'])) {
    $countryName = $_GET['country'];
    $sql = "SELECT * FROM pays WHERE nom = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("s", $countryName);
    $stmt->execute();
    $result = $stmt->get_result();
    if($result->num_rows > 0){
         $selectedCountry = $result->fetch_assoc();
         $stmt->close();

         $sqlCities = "SELECT * FROM ville WHERE id_pays = (SELECT id_pays FROM pays WHERE nom = ?)";
         $stmtCities = $conn->prepare($sqlCities);
         $stmtCities->bind_param("s", $countryName);
         $stmtCities->execute();
          $resultCities = $stmtCities->get_result();
          $selectedCountry['cities'] = [];
          while ($city = $resultCities->fetch_assoc()) {
              $selectedCountry['cities'][] = $city;
           }
        $stmtCities->close();
    }
}
$sql = "SELECT nom FROM pays";
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

$countries = [];
if ($result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        $countries[] = $row['nom'];
    }
}

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
      <div id="countryInfoModal" class="modal fixed hidden z-10 inset-0 overflow-y-auto bg-gray-900 bg-opacity-50">
         <div class="modal-content bg-white mx-auto my-20 p-6 border border-gray-200 rounded-md w-4/5 max-w-lg">
             <span class="close text-gray-600 float-right text-2xl font-bold cursor-pointer">×</span>
              <?php if ($selectedCountry): ?>
                    <h2 class="text-2xl font-bold mb-4"><?= $selectedCountry['nom'] ?></h2>
                    <p><strong>Population:</strong> <?= $selectedCountry['population'] ?? 'N/A' ?></p>
                    <p><strong>Languages:</strong> <?= $selectedCountry['langues'] ?? 'N/A' ?></p>
                   <?php if (isset($selectedCountry['cities']) && count($selectedCountry['cities']) > 0): ?>
                      <h3 class="text-xl font-bold mt-4 mb-2">Cities:</h3>
                     <ul>
                          <?php foreach ($selectedCountry['cities'] as $city): ?>
                              <li>
                                   <p><strong>Name:</strong> <?= $city['nom'] ?></p>
                                  <p><strong>Description:</strong> <?= $city['description'] ?? 'N/A' ?></p>
                                   <p><strong>Type:</strong> <?= $city['type'] ?? 'N/A' ?></p>
                                  <hr class="my-2">
                              </li>
                          <?php endforeach; ?>
                    </ul>
                 <?php else: ?>
                      <p>No cities for this country.</p>
                 <?php endif; ?>
              <?php else: ?>
                    <p>No country selected</p>
               <?php endif; ?>
         </div>
    </div>
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
               <div class="mt-4">
                    <button type="button" id="addCityButton" class="bg-blue-500 hover:bg-blue-700 text-white font-bold py-2 px-4 rounded">Add City</button>
                </div>
                  <input type="hidden" id="citiesInput" name="cities" >

               <div class="mt-6">
                    <input type="submit" name="add_country" value="Add Country" class="bg-green-500 hover:bg-green-700 text-white font-bold py-2 px-4 rounded focus:outline-none focus:shadow-outline cursor-pointer">
               </div>
            </form>
        </div>
    </div>
    <div class="flag-grid grid grid-cols-4 gap-8 justify-center p-5 mt-16">
        <?php
         foreach ($countries as $countryName) {
            if (isset($countryMap[$countryName])) {
                $code = $countryMap[$countryName];
                $flagUrl = "https://flagsapi.com/$code/$style/$size.png";
                echo '<div class="flag-item bg-white rounded-md shadow p-2 flex flex-col justify-center items-center">';
                echo '<a href="?country=' . urlencode($countryName) . '">';
                echo '<img src="' . $flagUrl . '" alt="' . $countryName . ' flag" class="max-w-full max-h-full block cursor-pointer">';
                echo '</a>';
                echo '<p class="mt-2 text-center">' . $countryName . '</p>';
                echo '</div>';
            }
        }
        ?>
    </div>
   <script>
    var modal = document.getElementById("addCountryModal");
    var btn = document.getElementById("addCountryBtn");
    var span = document.getElementsByClassName("close")[0];
    var addCityButton = document.getElementById("addCityButton");
    var citiesContainer = document.getElementById("cities-container");
    var citiesInput = document.getElementById("citiesInput");
     var countryInfoModal = document.getElementById('countryInfoModal');
     var countryInfoSpan = countryInfoModal.querySelector('.close');
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
     if (event.target === countryInfoModal) {
        countryInfoModal.style.display = 'none';
       }
    }

     countryInfoSpan.onclick = function() {
          countryInfoModal.style.display = "none";
        }

      addCityButton.addEventListener("click", function(){
        var cityDiv = document.createElement("div");
       cityDiv.classList.add("city-container", "border", "border-gray-300", "rounded-md", "p-3", "mb-4");
        cityDiv.innerHTML = `
            <h3 class="text-lg font-bold mb-2">City</h3>
           <div class="mb-2">
               <label for="city_nom" class="block text-gray-700 text-sm font-bold mb-1">City Name:</label>
                <input type="text" name="city_nom" required class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline">
            </div>
             <div class="mb-2">
                 <label for="city_description" class="block text-gray-700 text-sm font-bold mb-1">Description:</label>
                  <textarea name="city_description"  class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline"></textarea>
             </div>
              <div class="mb-2">
                  <label for="city_type" class="block text-gray-700 text-sm font-bold mb-1">Type:</label>
                   <select  name="city_type"  class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline">
                       <option value="capitale">Capital</option>
                       <option value="autre">Other</option>
                    </select>
            </div>
             <button class="remove-city bg-red-500 hover:bg-red-700 text-white font-bold py-1 px-2 rounded mt-2" type="button" >Remove</button>
        `;
        citiesContainer.appendChild(cityDiv);
    });

    citiesContainer.addEventListener("click", function(event) {
         if(event.target.classList.contains("remove-city")){
            event.target.parentElement.remove();
         }
      });
      document.querySelector("form").addEventListener("submit", function(event) {
       cities = [];
      citiesContainer.querySelectorAll(".city-container").forEach((cityContainer) => {
            const nom = cityContainer.querySelector("input[name='city_nom']").value;
            const description = cityContainer.querySelector("textarea[name='city_description']").value;
            const type = cityContainer.querySelector("select[name='city_type']").value;
            cities.push({ nom: nom, description: description, type: type });
      });
    citiesInput.value = JSON.stringify(cities);
     });
     </script>
</body>
</html>
<?php $conn->close(); ?>