<?php 

// Shortcode donation
function donationForm($atts)
{
  $allowedPostFields = gbs_allowedPostFields();
  
  //TODO validation
  /*$requiredFieldsValidators = array(
      "firstname" => array(),
      "lastname" => array(),
      "amount" => array("numeric"),
      "currency" => array("currency"),
      "address1" => array(),
      "address2" => array(),
      "country" => array(),
      "email" => array("email"),
      "payment" => array("numeric"),
  );*/

  $a = shortcode_atts( array('action' => false), $atts );
  $action = get_permalink( $a["action"] );



  // when form has been sent...
  if ($_POST) {

      // do some cosmetics on form data
      $keys = array_keys($_POST);

      // replace amount-other
      if (in_array('amount_other', $keys) && !empty($_POST['amount_other'])) {
          $_POST['amount'] = $_POST['amount_other'];
      }
      unset($_POST['amount_other']);

      // add payment-details
      if (in_array('payment', $keys) && $_POST['payment'] == "Lastschriftverfahren") {
          $_POST['payment-details'] = $_POST['payment-directdebit-frequency'] . ' | '  . $_POST['payment-directdebit-bankaccount'] . ' | ' . $_POST['payment-directdebit-method'];
      } else {
          $_POST['payment-details'] = '';
      }
      unset($_POST['payment-directdebit-frequency']);
      unset($_POST['payment-directdebit-bankaccount']);
      unset($_POST['payment-directdebit-method']);



      // Save donation as a custom post
      $new_post = array(
        'post_title' => 'New donation',
        'post_content' => 'New donation',
        'post_status' => 'pending',
        'post_type' => 'gbs_donation'
      );
      $postId = wp_insert_post($new_post);

      if ($postId) {
          $keys = array_keys($_POST);
          foreach ($keys as $key) {
              if (in_array($key, $allowedPostFields)) {
                  add_post_meta($postId, $key, $_POST[$key]);
              }
          }
      } else {
          echo "<p>An error has occured. Please contact us at <a href='mailto:donate@gbs-schweiz.org'>donate@gbs-schweiz.org</a>.</p>";
          return;
      }




      // output
      if ($_POST['payment'] == "PayPal") {
          echo gbs_paypalRedirect($_POST);
      } else if ($_POST['payment'] == "Skrill") {
          echo gbs_skrillRedirect($_POST);
      } else {
          echo "<p>An error has occured. Please contact us at <a href='mailto:donate@gbs-schweiz.org'>donate@gbs-schweiz.org</a>.</p>";
          return;
      }

      return;
  }
  
  ob_start();
?>

<!-- TODO: position the div below with shortcode arguments -->
<div>

<!-- the form -->
<form method="post" id="donationForm">
 
  <!-- scrollable root element -->
  <div id="wizard">
    <h2>Spenden</h2>
    <!-- status bar -->
    <ul id="status">
      <li class="active"><strong>1. Betrag</strong></li>
      <li><strong>2. Adresse</strong></li>
      <li><strong>3. Zahlungsart</strong></li>
    </ul>
 
    <!-- scrollable items -->
    <div class="items">
      <!-- pages -->
      <div class="page">
        <h3>Meine Spende</h3>
        <ul id="amounts">
          <li class="amount-cont first" id="amount-cont-1">
            <input type="radio" class="radio" name="amount" value="15.00" tabindex="1" id="amount-15">
            <label for="amount-15"><span class="cur">CHF</span> 15</label>
          </li>
          
          <li class="amount-cont" id="amount-cont-2">
            <input type="radio" class="radio" name="amount" value="35.00" tabindex="2" id="amount-35">
            <label for="amount-35"><span class="cur">CHF</span> 35</label>
          </li>
          
          <li class="amount-cont" id="amount-cont-3">
            <input type="radio" class="radio" name="amount" value="50.00" tabindex="3" id="amount-50">
            <label for="amount-50"><span class="cur">CHF</span> 50</label>
          </li>
          
          <li class="amount-cont" id="amount-cont-4">
            <input type="radio" class="radio" name="amount" value="100.00" tabindex="4" id="amount-100">
            <label for="amount-100"><span class="cur">CHF</span> 100</label>
          </li>
          
          <li class="amount-cont" id="amount-cont-5">
            <input type="radio" class="radio" name="amount" value="250.00" tabindex="5" id="amount-250">
            <label for="amount-250"><span class="cur">CHF</span> 250</label>
          </li>
          
          <li class="amount-cont" id="amount-cont-6">
            <input type="radio" class="radio" name="amount" value="500.00" tabindex="6" id="amount-500">
            <label for="amount-500"><span class="cur">CHF</span> 500</label>
          </li>
          
          <li class="amount-cont" id="amount-cont-7">
            <input type="radio" class="radio" name="amount" value="1000.00" tabindex="7" id="amount-1000">
            <label for="amount-1000"><span class="cur">CHF</span> 1'000</label>
          </li>
          
          <li class="amount-cont last" id="amount-cont-8">
            <label for="amount-other">Other</label>
            <input type="text" class="text" name="amount_other" id="amount-other" placeholder="Anderer Betrag" tabindex="9">
          </li>

        </ul>
        <div id="currency-cont">
          <div id="currency-link">
            <a href="#">Andere Währung »</a>
          </div>
          <div id="currency-form">
            <!-- <label for="currency">Währung</label> -->
            <select name="currency" id="currency">
              <option value="USD">$</option>
              <option value="GBP">£</option>
              <option value="CHF" selected="selected">CHF</option>
              <option value="EUR">€</option>
            </select>
          </div>
        </div>
      </div>

      <div class="page">
        <ul id="form-fields">
          <li class="required" id="title-cont">
            <p class="title_error hidden error"></p>
            <label for="title" class="title_related">Anrede*</label>
            <select name="title" id="title" tabindex="11">
              <option value>Bitte wählen</option>
              <option>Herr</option>
              <option>Frau</option>
            </select>
          </li>
          <li class="required" id="firstname-cont">
            <p class="firstname_error hidden error"></p>
            <label for="firstname" class="firstname_related">Vorname*</label>
            <input type="text" class="text" name="firstname" id="firstname" tabindex="12">
          </li>
          <li class="required" id="lastname-cont">
            <p class="lastname_error hidden error"></p>
            <label for="lastname" class="lastname_related">Nachname*</label>
            <input type="text" class="text" name="lastname" id="lastname" tabindex="13">
          </li>
          <li class="required" id="addr1-cont">
            <p class="addr1_error hidden error"></p>
            <label for="address1" class="addr1_related">Adresse (Zeile 1)*</label>
            <input type="text" class="text" name="address1" id="address1" tabindex="14">
          </li>
          <li class="required" id="addr2-cont">
            <p class="addr2_error hidden error"></p>
            <label for="address2" class="addr2_related">Adresse (Zeile 2)*</label>
            <input type="text" class="text" name="address2" id="address2" tabindex="15">
          </li>
          <!--<li id="addr3-cont">
            <p class="addr3_error hidden error"></p>
            <label for="address3" class="addr3_related">Adresse (Zeile 3)</label>
            <input type="text" class="text" name="address3" id="address3" tabindex="5">
          </li>-->
          <li class="required" id="country-cont">
            <p class="country_error hidden error"></p>
            <label for="country" class="country_related">Land*</label>
            <select name="country" id="country" tabindex="16">
<option value>Bitte wählen</option>
<option value="CH">Schweiz</option>
<option value>--</option>
<option value="AF">Afghanistan</option>
<option value="EG">Ägypten</option>
<option value="AX">Aland</option>
<option value="AL">Albanien</option>
<option value="DZ">Algerien</option>
<option value="AS">Amerikanisch-Samoa</option>
<option value="VI">Amerikanische Jungferninseln</option>
<option value="AD">Andorra</option>
<option value="AO">Angola</option>
<option value="AI">Anguilla</option>
<option value="AQ">Antarktis</option>
<option value="AG">Antigua und Barbuda</option>
<option value="GQ">Äquatorialguinea</option>
<option value="AR">Argentinien</option>
<option value="AM">Armenien</option>
<option value="AW">Aruba</option>
<option value="AC">Ascension</option>
<option value="AZ">Aserbaidschan</option>
<option value="ET">Äthiopien</option>
<option value="AU">Australien</option>
<option value="BS">Bahamas</option>
<option value="BH">Bahrain</option>
<option value="BD">Bangladesch</option>
<option value="BB">Barbados</option>
<option value="BE">Belgien</option>
<option value="BZ">Belize</option>
<option value="BJ">Benin</option>
<option value="BM">Bermuda</option>
<option value="BT">Bhutan</option>
<option value="BO">Bolivien</option>
<option value="BA">Bosnien und Herzegowina</option>
<option value="BW">Botswana</option>
<option value="BV">Bouvetinsel</option>
<option value="BR">Brasilien</option>
<option value="BN">Brunei</option>
<option value="BG">Bulgarien</option>
<option value="BF">Burkina Faso</option>
<option value="BI">Burundi</option>
<option value="CL">Chile</option>
<option value="CN">China</option>
<option value="CK">Cookinseln</option>
<option value="CR">Costa Rica</option>
<option value="CI">Cote d'Ivoire</option>
<option value="DK">Dänemark</option>
<option value="DE">Deutschland</option>
<option value="DG">Diego Garcia</option>
<option value="DM">Dominica</option>
<option value="DO">Dominikanische Republik</option>
<option value="DJ">Dschibuti</option>
<option value="EC">Ecuador</option>
<option value="SV">El Salvador</option>
<option value="ER">Eritrea</option>
<option value="EE">Estland</option>
<option value="EU">Europäische Union</option>
<option value="FK">Falklandinseln</option>
<option value="FO">Färöer</option>
<option value="FJ">Fidschi</option>
<option value="FI">Finnland</option>
<option value="FR">Frankreich</option>
<option value="GF">Französisch-Guayana</option>
<option value="PF">Französisch-Polynesien</option>
<option value="GA">Gabun</option>
<option value="GM">Gambia</option>
<option value="GE">Georgien</option>
<option value="GH">Ghana</option>
<option value="GI">Gibraltar</option>
<option value="GD">Grenada</option>
<option value="GR">Griechenland</option>
<option value="GL">Grönland</option>
<option value="GB">Großbritannien</option>
<option value="CP">Guadeloupe</option>
<option value="GU">Guam</option>
<option value="GT">Guatemala</option>
<option value="GG">Guernsey</option>
<option value="GN">Guinea</option>
<option value="GW">Guinea-Bissau</option>
<option value="GY">Guyana</option>
<option value="HT">Haiti</option>
<option value="HM">Heard und McDonaldinseln</option>
<option value="HN">Honduras</option>
<option value="HK">Hongkong</option>
<option value="IN">Indien</option>
<option value="ID">Indonesien</option>
<option value="IQ">Irak</option>
<option value="IR">Iran</option>
<option value="IE">Irland</option>
<option value="IS">Island</option>
<option value="IL">Israel</option>
<option value="IT">Italien</option>
<option value="JM">Jamaika</option>
<option value="JP">Japan</option>
<option value="YE">Jemen</option>
<option value="JE">Jersey</option>
<option value="JO">Jordanien</option>
<option value="KY">Kaimaninseln</option>
<option value="KH">Kambodscha</option>
<option value="CM">Kamerun</option>
<option value="CA">Kanada</option>
<option value="IC">Kanarische Inseln</option>
<option value="CV">Kap Verde</option>
<option value="KZ">Kasachstan</option>
<option value="QA">Katar</option>
<option value="KE">Kenia</option>
<option value="KG">Kirgisistan</option>
<option value="KI">Kiribati</option>
<option value="CC">Kokosinseln</option>
<option value="CO">Kolumbien</option>
<option value="KM">Komoren</option>
<option value="CG">Kongo</option>
<option value="HR">Kroatien</option>
<option value="CU">Kuba</option>
<option value="KW">Kuwait</option>
<option value="LA">Laos</option>
<option value="LS">Lesotho</option>
<option value="LV">Lettland</option>
<option value="LB">Libanon</option>
<option value="LR">Liberia</option>
<option value="LY">Libyen</option>
<option value="LI">Liechtenstein</option>
<option value="LT">Litauen</option>
<option value="LU">Luxemburg</option>
<option value="MO">Macao</option>
<option value="MG">Madagaskar</option>
<option value="MW">Malawi</option>
<option value="MY">Malaysia</option>
<option value="MV">Malediven</option>
<option value="ML">Mali</option>
<option value="MT">Malta</option>
<option value="MA">Marokko</option>
<option value="MH">Marshallinseln</option>
<option value="MQ">Martinique</option>
<option value="MR">Mauretanien</option>
<option value="MU">Mauritius</option>
<option value="YT">Mayotte</option>
<option value="MK">Mazedonien</option>
<option value="MX">Mexiko</option>
<option value="FM">Mikronesien</option>
<option value="MD">Moldawien</option>
<option value="MC">Monaco</option>
<option value="MN">Mongolei</option>
<option value="MS">Montserrat</option>
<option value="MZ">Mosambik</option>
<option value="MM">Myanmar</option>
<option value="NA">Namibia</option>
<option value="NR">Nauru</option>
<option value="NP">Nepal</option>
<option value="NC">Neukaledonien</option>
<option value="NZ">Neuseeland</option>
<option value="NT">Neutrale Zone</option>
<option value="NI">Nicaragua</option>
<option value="NL">Niederlande</option>
<option value="AN">Niederländische Antillen</option>
<option value="NE">Niger</option>
<option value="NG">Nigeria</option>
<option value="NU">Niue</option>
<option value="KP">Nordkorea</option>
<option value="MP">Nördliche Marianen</option>
<option value="NF">Norfolkinsel</option>
<option value="NO">Norwegen</option>
<option value="OM">Oman</option>
<option value="AT">Österreich</option>
<option value="PK">Pakistan</option>
<option value="PS">Palästina</option>
<option value="PW">Palau</option>
<option value="PA">Panama</option>
<option value="PG">Papua-Neuguinea</option>
<option value="PY">Paraguay</option>
<option value="PE">Peru</option>
<option value="PH">Philippinen</option>
<option value="PN">Pitcairninseln</option>
<option value="PL">Polen</option>
<option value="PT">Portugal</option>
<option value="PR">Puerto Rico</option>
<option value="RE">Réunion</option>
<option value="RW">Ruanda</option>
<option value="RO">Rumänien</option>
<option value="RU">Russische Föderation</option>
<option value="SB">Salomonen</option>
<option value="ZM">Sambia</option>
<option value="WS">Samoa</option>
<option value="SM">San Marino</option>
<option value="ST">São Tomé und Príncipe</option>
<option value="SA">Saudi-Arabien</option>
<option value="SE">Schweden</option>
<option value="CH">Schweiz</option>
<option value="SN">Senegal</option>
<option value="CS">Serbien und Montenegro</option>
<option value="SC">Seychellen</option>
<option value="SL">Sierra Leone</option>
<option value="ZW">Simbabwe</option>
<option value="SG">Singapur</option>
<option value="SK">Slowakei</option>
<option value="SI">Slowenien</option>
<option value="SO">Somalia</option>
<option value="ES">Spanien</option>
<option value="LK">Sri Lanka</option>
<option value="SH">St. Helena</option>
<option value="KN">St. Kitts und Nevis</option>
<option value="LC">St. Lucia</option>
<option value="PM">St. Pierre und Miquelon</option>
<option value="VC">St. Vincent/Grenadinen (GB)</option>
<option value="ZA">Südafrika, Republik</option>
<option value="SD">Sudan</option>
<option value="KR">Südkorea</option>
<option value="SR">Suriname</option>
<option value="SJ">Svalbard und Jan Mayen</option>
<option value="SZ">Swasiland</option>
<option value="SY">Syrien</option>
<option value="TJ">Tadschikistan</option>
<option value="TW">Taiwan</option>
<option value="TZ">Tansania</option>
<option value="TH">Thailand</option>
<option value="TL">Timor-Leste</option>
<option value="TG">Togo</option>
<option value="TK">Tokelau</option>
<option value="TO">Tonga</option>
<option value="TT">Trinidad und Tobago</option>
<option value="TA">Tristan da Cunha</option>
<option value="TD">Tschad</option>
<option value="CZ">Tschechische Republik</option>
<option value="TN">Tunesien</option>
<option value="TR">Türkei</option>
<option value="TM">Turkmenistan</option>
<option value="TC">Turks- und Caicosinseln</option>
<option value="TV">Tuvalu</option>
<option value="UG">Uganda</option>
<option value="UA">Ukraine</option>
<option value="HU">Ungarn</option>
<option value="UY">Uruguay</option>
<option value="UZ">Usbekistan</option>
<option value="VU">Vanuatu</option>
<option value="VA">Vatikanstadt</option>
<option value="VE">Venezuela</option>
<option value="AE">Vereinigte Arabische Emirate</option>
<option value="US">Vereinigte Staaten von Amerika</option>
<option value="VN">Vietnam</option>
<option value="WF">Wallis und Futuna</option>
<option value="CX">Weihnachtsinsel</option>
<option value="BY">Weißrussland</option>
<option value="EH">Westsahara</option>
<option value="CF">Zentralafrikanische Republik</option>
<option value="CY">Zypern</option>
            </select>
          </li>
          <li class="required" id="email-cont">
            <p class="email_error hidden error"></p>
            <label for="email" class="email_related">E-Mail*</label>
            <input type="text" class="text" name="email" id="email" tabindex="17">
          </li>
          <li class="last" id="phone-cont">
            <p class="phone_error hidden error"></p>
            <label for="phone" class="phone_related">Telefon</label>
            <input type="text" class="text" name="phone" id="phone" tabindex="18">
          </li>
        </ul>
      </div>



      <div class="page">
        <h3>Wählen Sie eine Zahlungsart</h3>
        <ul id="payment-method">
          <li>
            <input type="radio" class="radio" name="payment" value="PayPal" tabindex="19" id="payment-paypal">
            <label for="payment-paypal">PayPal</label>
          </li>
          <li>
            <input type="radio" class="radio" name="payment" value="Banktransfer" tabindex="20" id="payment-banktransfer">
            <label for="payment-banktransfer">Banküberweisung</label>
          </li>
          <li>
            <input type="radio" class="radio" name="payment" value="Lastschriftverfahren" tabindex="21" id="payment-directdebit">
            <label for="payment-directdebit">
              Lastschriftverfahren
              <div id="directdebit-details">
                <ul>
                  <li>
                    <label for="payment-directdebit-frequency">
                      Wie oft*
                    </label>
                    <select name="payment-directdebit-frequency" id="payment-directdebit-frequency">
                      <option value="monthly">monatlich</option>
                      <option value="quarterly">viertjährlich</option>
                      <option value="half-yearly">halbjährlich</option>
                      <option value="yearly">jährlich</option>
                    </select>
                  </li>
                  <li>
                    <label for="payment-directdebit-account">
                      Postkonto oder IBAN-Nummer*
                    </label>
                    <input type="text" name="payment-directdebit-bankaccount" id="payment-directdebit-account">
                  </li>
                  <li>
                    <label for="payment-directdebit-method">
                      Spendenauftrag*
                    </label>
                    <select name="payment-directdebit-method" id="payment-directdebit-method">
                      <option value="print at home">zuhause ausdrucken</option>
                      <option value="post">per Post zusenden</option>
                    </select>
                  </li>
                </ul>
              </div>
            </label>
          </li>
          <li>
            <input type="radio" class="radio" name="payment" value="Skrill" tabindex="19" id="payment-skrill">
            <label for="payment-skrill">Skrill</label>
          </li>
          <!-- <li>
            <input type="radio" class="radio" name="payment" value="Banktransfer" tabindex="19" id="payment-bitpay">
            <label for="payment-bitpay">BitPay</label>
          </li> -->
        </ul>
      </div>

    </div>
    <div id="confirmButtonBox">
      <button type="button" id="confirmButton" class="next confirm">Bestätigen »</button>
    </div>
  </div>

</form>

<div id="drawer">Bitte alle obligatorischen Felder ausfüllen.</div>


</div>

<?php
  $content = ob_get_clean();
  return $content;
}



