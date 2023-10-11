var haidaApp = angular.module('haidaApp', []);

/**
 * Controller
 */
haidaApp.controller('mainController', function mainController($scope, $http) {

  console.log('Haida main controller.');

  var accountsSync = [];
  var sheetSync = [];
  $scope.date = sessionStorage.getItem("date", false);
  if (!$scope.date) {
    $scope.date = new Date();
  }
  $scope.year = $scope.date.getFullYear();
  $scope.month = $scope.date.getMonth() + 1;
  $scope.day = $scope.date.getDay();
  $scope.currentRow = null;

  /**
   * Initialization
   */
  $scope.addEmptyField = function () {
    $scope.sheet.push({
      id: $scope.sheet.length + 1,
      year: $scope.year,
      month: $scope.month,
      day: $scope.day,
      fromName: '',
      fromAid: null,
      toName: '',
      toAid: null,
      value: '',
      description: '',
      status: 'unmodified',
    });
  }

  /**
   * Initialization
   */
  $scope.initialization = function () {
    $scope.accounts = [];
    $scope.sheet = [];
    console.log('Inicialización');
    var parameters = { init: true };
    $http.get('/haida.php', parameters, { headers: { 'Content-Type': 'application/json' }})
    .then(function successCallback(response) {
      console.log('GET', response);
      $scope.accounts = response.data.accounts;
      accountsSync = [...$scope.accounts];
      $scope.sheet.push(...$scope.preprocessSheet(response.data.sheet));
      sheetSync = [...$scope.sheet];
      $scope.addEmptyField();
      $scope.lastAccountId = response.data.last_account_id;
    }, function errorCallback(response) {
      console.log('ERROR');
    });
  }

  /**
   * Preprocess Sheet
   */
  $scope.preprocessSheet = function (sheet) {
    return sheet.map(item => {
      var date = new Date(parseInt(item.date) * 1000);
      return {
        id: item.id,
        year: date.getFullYear(),
        month: date.getMonth() + 1,
        day: date.getDay(),
        fromName: $scope.getAccountName(item.credit),
        fromAid: item.credit,
        toName: $scope.getAccountName(item.debit),
        toAid: item.debit,
        value: parseFloat(item.value),
        description: item.description,
        status: 'unmodified',
      };
    });
  }

  $scope.initialization();

  $scope.sync = false;
  $scope.syncInProgress = false;

  /**
   * Run Sync
   */
  $scope.runSync = function () {
    if ($scope.sync && !$scope.syncInProgress) {
      console.log('sync in progress');
      $scope.syncInProgress = true;

      var upstream = [];

      $scope.sheet.forEach(item => {
        if (item.status != 'unmodified') {
          upstream.push(item);
        }
      });

      console.log($scope.sheet);
      console.log(upstream);

      var parameters = JSON.stringify({ 'upstream': upstream });

      $http.post('/haida.php', parameters, { headers: { 'Content-Type': 'application/json' }})
        .then(function successCallback(response) {
          console.log('POST', response);
          accountsSync.push(...response.data.accounts);
          $scope.accounts = [...accountsSync];
          sheetSync.push(...$scope.preprocessSheet(response.data.sheet));
          response.data.deleted.forEach((function(element) {
            $scope.deleteRowConfirm(element);
          }));
          $scope.preprocessSheet(response.data.updated).forEach((function(element) {
            $scope.updateRow(element);
          }));
          $scope.sheet = sheetSync;
          $scope.addEmptyField();
          $scope.sync = false;
          $scope.syncInProgress = false;
        }, function errorCallback(response) {
          console.log('ERROR');
          $scope.sync = false;
          $scope.syncInProgress = false;
      });

    }
  }

  /**
   * Save Row
   */
  $scope.saveRow = function (index) {
    var data = $scope.sheet[index];
    var fromAccount = $scope.emptyAccount();
    if (data.fromName != '') {
      fromAccount = $scope.getAccount(data.fromName);
      if (!fromAccount) {
        fromAccount = $scope.addAccount(data.fromName, -1 * data.value);
      }
    }

    var toAccount = $scope.emptyAccount();
    if (data.toName != '') {
      toAccount = $scope.getAccount(data.toName);
      if (!toAccount) {
        toAccount = $scope.addAccount(data.toName, data.value);
      }
    }

    if (index === $scope.sheet.length - 1) {
      $scope.sheet[index].fromAid = fromAccount.id;
      $scope.sheet[index].toAid = toAccount.id;
      $scope.sheet[index].status = 'added';
      $scope.addEmptyField();
    }
    else { 
      $scope.sheet[index].fromAid = fromAccount.id;
      $scope.sheet[index].toAid = toAccount.id;
      $scope.sheet[index].status = 'edited';
      $scope.sheet[index].fromName = fromAccount.name;
      $scope.sheet[index].toName = toAccount.name;
    }

  };

  /**
   * Empty account
   */
  $scope.emptyAccount = function () {
    return {id:null, name:''};
  }


  /**
   * Delete Row
   */
  $scope.deleteRow = function (index, item) {
    // if (confirm("Confirmar eliminación de registro")) {
      item.status = 'deleted';
      var item_clon = item;
      $scope.update(index, item_clon);
    // }
  }

  /**
   * Update
   */
  $scope.update = function (index, item) {
    $scope.sync = true;

    console.log('current row', $scope.currentRow);

    // Skip if item.value is empty
    if (item.value == '' || item.value === null) {
      return;
    }

    var old_value = $scope.currentRow.value ?? 0;
    var fromName = item.fromName;
    var oldFromName = $scope.currentRow.fromName;
    var toName = item.toName;
    var oldToName = $scope.currentRow.toName;

    old_value = old_value == '' ? 0 : parseFloat(old_value);

    // Update current value in current row.
    $scope.currentRow = { ...item };

    // Detect if new row and skip save.
    if (index !== $scope.sheet.length - 1 && item.status != 'deleted') {
      $scope.saveRow(index);
    }

    $scope.accounts.forEach(account => {

      if (account.name == fromName && fromName == oldFromName) {
          account.value = account.value - item.value + old_value;
      }
      else if (account.name == fromName && fromName != oldFromName && (account.hasOwnProperty('new') && !account.new)) {
        account.value = account.value - item.value;
      }
      else if (account.name == oldFromName && fromName != oldFromName) {
        account.value = account.value + item.value;
      }
      else if (account.name == fromName && oldFromName == '') {
        account.value = account.value - item.value + old_value;
      }
      else if (account.name == fromName && item.status == 'deleted') {
        account.value = account.value + item.value;
      }

      if (account.name == toName && toName == oldToName) {
        account.value = account.value + item.value - old_value;
      }
      else if (account.name == toName && toName != oldToName && (account.hasOwnProperty('new') && !account.new)) {
        account.value = account.value + item.value;
      }
      else if (account.name == oldToName && toName != oldToName) {
        account.value = account.value - item.value;
      }
      else if (account.name == toName && oldToName == '') {
        account.value = account.value + item.value - old_value;
      }
      else if (account.name == toName && item.status == 'deleted') {
        account.value = account.value - item.value;
      }

      if (account.new) {
        account.new = false;
      }

    });
  }

  /**
   * Get Account
   */
  $scope.getAccount = function (accountName) {
    var searchResult = false;
    $scope.accounts.forEach((value, index) => {      
      if (value.name == accountName) {
        searchResult = value;
        return true;
      }
    });
    return searchResult;
  }

  /**
   * Add Account
   */
  $scope.addAccount = function (accountName, value) {
    var names = accountName.split(/\s+/);
    var pid = 0;
    var name = '';
    var new_account = {};
    var account;
    names.forEach((piece, index) => {
      $scope.lastAccountId++;
      if (index > 0) {
        pid = $scope.accounts.slice(-1)[0].id;
        name += ' ';
      }
      name += piece;
      account = $scope.getAccount(name);
      if (account == false) {
        new_account = {
          id: $scope.lastAccountId,
          pid: pid,
          name: name,
          value: value,
          new: true
        };
        $scope.accounts.push(new_account);
      }
      else {
        account.value = account.value + value;
      }
    });
    return new_account;
  }

  /**
   * Get Account Name
   */
  $scope.getAccountName = function (accountId) {
    for (var i=0, iLen=$scope.accounts.length; i<iLen; i++) {    
      if ($scope.accounts[i].id == accountId) {
        return $scope.accounts[i].name;
      }
    }
  }

  /**
   * Run Balance
   */
  $scope.runBalance = function () {
  }

  /**
   * Delete row confirm
   */
  $scope.deleteRowConfirm = function (item) {
    for (var i = 0; i < sheetSync.length; i++) {
      if (sheetSync[i].id === item.id) {
        sheetSync.splice(i, 1);
        break;
      }
    }
  }

  /**
   * Update row
   */
  $scope.updateRow = function (item) {
    for (var i = 0; i < sheetSync.length; i++) {
      if (sheetSync[i].id === item.id) {
        sheetSync[i] = item;
        break;
      }
    }
  }

  /**
   * Focus
   */
  $scope.focus = function (index, item) {
    if ($scope.currentIndex != index) {
      $scope.currentIndex = index;
      $scope.currentRow = { ...item };
      console.log('focus current row', $scope.currentRow);
    }
  }

  /**
   * Blur
   */
  $scope.blur = function (index, item) {
    $scope.update(index, item);
  }

});
