/*
  Requirement: Add interactivity and data management to the Admin Portal.

  Instructions:
  1. This file is loaded by the <script src="manage_users.js" defer> tag in manage_users.html.
     The 'defer' attribute guarantees the DOM is fully parsed before this script runs.
  2. Implement the JavaScript functionality as described in the TODO comments.
  3. All data is fetched from and written to the PHP API at '../api/index.php'.
     The local 'users' array is used only as a client-side cache for search and sort.
*/

// --- Global Data Store ---
// This array will be populated with data fetched from the PHP API.
// It acts as a client-side cache so search and sort work without extra network calls.
let users = [];

// --- Element Selections ---
// We can safely select elements here because 'defer' guarantees
// the HTML document is parsed before this script runs.

// TODO: Select the user table body element with id="user-table-body".
const userTableBody = document.getElementById('user-table-body');

// TODO: Select the "Add User" form with id="add-user-form".
const addUserForm = document.getElementById('add-user-form');


// TODO: Select the "Change Password" form with id="password-form".
const changePasswordForm = document.getElementById('password-form');


// TODO: Select the search input field with id="search-input".
const searchInput = document.getElementById('search-input');


// TODO: Select all table header (th) elements inside the thead of id="user-table".
const tableHeaders = document.querySelectorAll('#user-table thead th');


// --- Functions ---

/**
 * TODO: Implement the createUserRow function.
 * This function takes a user object { id, name, email, is_admin } and returns a <tr> element.
 * The <tr> should contain:
 * 1. A <td> for the user's name.
 * 2. A <td> for the user's email.
 * 3. A <td> showing admin status, e.g. "Yes" if is_admin === 1, otherwise "No".
 * 4. A <td> containing two buttons:
 *    - An "Edit" button with class "edit-btn" and a data-id attribute set to the user's id.
 *    - A "Delete" button with class "delete-btn" and a data-id attribute set to the user's id.
 */
function createUserRow(user) {
  // ... your implementation here ...
  const row = document.createElement('tr');

  const nameCell = document.createElement('td');
  nameCell.textContent = user.name;
  row.appendChild(nameCell);

  const emailCell = document.createElement('td')
  emailCell.textContent = user.email;
  row.appendChild(emailCell);

  const adminCell = document.createElement('td')
  adminCell.textContent = user.is_admin===1 ? 'Yes' : 'No';
  row.appendChild(adminCell);

  const actionsCell = document.createElement('td');
  const ediBtn = document.createElement('button');
  ediBtn.textContent = 'Edit';
  ediBtn.className = 'edit-btn';
  ediBtn.setAttribute('data-id',user.id);
  actionsCell.appendChild(ediBtn);

  const deleteBtn = document.createElement('button')
  deleteBtn.textContent='Delete';
  deleteBtn.className ='delete-btn';
  deleteBtn.setAttribute('data-id',user.id);
  actionsCell.appendChild(deleteBtn);

  row.appendChild(actionsCell);

  return row;

  

}

/**
 * TODO: Implement the renderTable function.
 * This function takes an array of user objects.
 * It should:
 * 1. Clear the current content of the userTableBody.
 * 2. Loop through the provided array of users.
 * 3. For each user, call createUserRow and append the returned <tr> to userTableBody.
 */
function renderTable(userArray) {
  // ... your implementation here ...
  userTableBody.innerHTML='';

  for(const user of userArray){
    const row = createUserRow(user);
    userTableBody.appendChild(row);
  }
}

/**
 * TODO: Implement the handleChangePassword function.
 * This function is called when the "Update Password" form is submitted.
 * It should:
 * 1. Prevent the form's default submission behaviour.
 * 2. Get the values from "current-password", "new-password", and "confirm-password" inputs.
 * 3. Perform client-side validation:
 *    - If "new-password" and "confirm-password" do not match, show an alert: "Passwords do not match."
 *    - If "new-password" is less than 8 characters, show an alert: "Password must be at least 8 characters."
 * 4. If validation passes, send a POST request to '../api/index.php?action=change_password'
 *    with a JSON body: { id, current_password, new_password }
 *    where 'id' is the currently logged-in admin's user id.
 * 5. On success, show an alert: "Password updated successfully!" and clear all three inputs.
 * 6. On failure, show the error message returned by the API.
 */
function handleChangePassword(event) {
  // ... your implementation here ...
  event.preventDefault();
  const currentPassword = document.getElementById('current-password').value;
  const newPassword = document.getElementById('new-password').value;
  const confirmPassword = document.getElementById('confirm-password').value;

  if(newPassword !== confirmPassword){
    alert('Passwords do not match.');
    return;
  }
  
  if(newPassword.length < 8){
    alert('Password must be at least 8 characters.');
    return;
  }

  const adminId = localStorage.getItem('userId');
  if(!adminId){
    alert('You must be logged in as admin.');
    return;
  }

  fetch('../api/index.php?action=change_password',{
    method: 'POST',
    headers: {'Content-Type':'application/json'},
    body: JSON.stringify({
      id: parseInt(adminId),
      current_password: currentPassword,
      new_password: newPassword
    })
  })
  .then(response => response.json())
  .then(data => {
    if(data.success){
      alert('Password updated successfully!');
      document.getElementById('current-password').value = '';
      document.getElementById('new-password').value = '';
      document.getElementById('confirm-password').value = '';
    }else{
      alert(data.message || 'Failed to update password.');
    }
  })
  .catch(error => {
    console.error('Error:', error);
    alert('An error occurred. Please try again.')
  });
}


/**
 * TODO: Implement the handleAddUser function.
 * This function is called when the "Add User" form is submitted.
 * It should:
 * 1. Prevent the form's default submission behaviour.
 * 2. Get the values from "user-name", "user-email", "default-password", and "is-admin".
 * 3. Perform client-side validation:
 *    - If name, email, or password are empty, show an alert: "Please fill out all required fields."
 *    - If password is less than 8 characters, show an alert: "Password must be at least 8 characters."
 * 4. If validation passes, send a POST request to '../api/index.php'
 *    with a JSON body: { name, email, password, is_admin }
 * 5. On success (HTTP 201), re-fetch the full user list by calling loadUsersAndInitialize()
 *    so the table reflects the new record from the database.
 * 6. Clear the form inputs on success.
 * 7. On failure, show the error message returned by the API.
 */
function handleAddUser(event) {
  // ... your implementation here ...
  event.preventDefault();

  const name = document.getElementById('user-name').value;
  const email = document.getElementById('user-email').value;
  const password = document.getElementById('default-password').value;
  const isAdmin = document.getElementById('is-admin').value;

  if(!name || !email || !password){
    alert('Please fill out all required fields.');
    return;
  }
  if(password.length<8){
    alert('Password must be at least 8 characters.');
    return;
  }

  fetch('../api/index.php',{
    method: 'POST',
    headers: {'Content-Type':'application/json'},
    body:JSON.stringify({
      name:name,
      email:email,
      password:password,
      is_admin: parseInt(isAdmin)
    })
  })
  .then(response=>response.json())
  .then(data=>{
    if(data.success || response.status ===201){
      alert('User added successfully!');

      document.getElementById('user-name').value='';
      document.getElementById('user-email').value='';
      document.getElementById('default-password').value='';
      document.getElementById('is-admin').value='0';

      loadUsersAndInitialize();
    }else{
      alert(data.message || 'Failed to add user');
    }
  })

  .catch(error=>{
    console.error('Error:',error);
    alert('An error occurred. Please try again.');
  });
}


/**
 * TODO: Implement the handleTableClick function.
 * This function is an event listener on userTableBody (event delegation).
 * It should:
 * 1. Check if the clicked element has the class "delete-btn".
 * 2. If it is a "delete-btn":
 *    - Get the data-id attribute from the button (this is the user's database id).
 *    - Send a DELETE request to '../api/index.php?id=' + id.
 *    - On success, remove the user from the local 'users' array and call renderTable(users).
 *    - On failure, show the error message returned by the API.
 * 3. If it is an "edit-btn":
 *    - Get the data-id attribute from the button.
 *    - (Optional) Populate an edit form or prompt with the user's current data
 *      and send a PUT request to '../api/index.php' with the updated fields.
 */
function handleTableClick(event) {
  // ... your implementation here ...
  const target = event.target;

  if(target.classList.contains('delete-btn')){
    const userId = target.getAttribute('data-id');

    if(confirm('Are you sure you want to delete this user?')){
      fetch(`../api/index.php?id=${userId}`,{
        method:'DELETE'
      })
      .then(response=>response.json())
      .then(data=>{
        if(data.success){
          users=users.filter(user=>user.id !=userId);
          renderTable(users);
          alert('User deleted successfully!');
        }else{
          alert(data.message || 'Failed to delete user.');
        }
      })
      .catch(error=>{
        console.error('Error:',error);
        alert('An error occurred. Please try again.')
      });
    }
  }

  if(target.classList.contains('edit-btn')){
    const userId = target.getAttribute('data-id');
    const user = users.find(u=>u.id==userId);

    if(user){
      const newName = prompt('Enter new name:', user.name);
      const newEmail = prompt('Enter new email:', user.email);
      const newAdmin = confirm('Is this user an admin? Click OK for Yes, Cancel for No');

      if(newName && newEmail){
        fetch('../api/index.php',{
          method:'PUT',
          headers:{'Content-Type':'application/json'},
          body:JSON.stringify({
            id:parseInt(userId),
            name:newName,
            email:newEmail,
            is_admin:newAdmin ? 1:0
          })
        })

        .then(response=>response.json())
        .then(data=>{
          if(data.success){
            alert('User updated successfully!');
            loadUsersAndInitialize();
          }else{
            alert(data.message || 'Failed to update user.')
          }
        })
        .catch(error=>{
          console.error('Error',error);
          alert('An error occurred');
        });
      }
    }
  }
}

/**
 * TODO: Implement the handleSearch function.
 * This function is called on the "input" event of the searchInput.
 * It should:
 * 1. Get the search term from searchInput.value and convert it to lowercase.
 * 2. If the search term is empty, call renderTable(users) to show all users.
 * 3. Otherwise, filter the local 'users' array to find users whose name or email
 *    (converted to lowercase) includes the search term.
 * 4. Call renderTable with the filtered array.
 *    (This filters the client-side cache only; no extra API call is needed.)
 */
function handleSearch(event) {
  // ... your implementation here ...
  const searchTerm = searchInput.value.toLowerCase();
  if(searchTerm===''){
    renderTable(users);
  }else{
    const filteredUsers = users.filter(user=>
      user.name.toLowerCase().includes(searchTerm)||
      user.email.toLowerCase().includes(searchTerm)
    );
    renderTable(filteredUsers);
  }
}

/**
 * TODO: Implement the handleSort function.
 * This function is called when any <th> in the thead is clicked.
 * It should:
 * 1. Identify which column was clicked using event.currentTarget.cellIndex.
 * 2. Map the cell index to a property name:
 *    - index 0 -> 'name'
 *    - index 1 -> 'email'
 *    - index 2 -> 'is_admin'
 * 3. Toggle sort direction using a data-sort-dir attribute on the <th>
 *    between "asc" and "desc".
 * 4. Sort the local 'users' array in place using array.sort():
 *    - For 'name' and 'email', use localeCompare for string comparison.
 *    - For 'is_admin', compare the values as numbers.
 * 5. Respect the sort direction (ascending or descending).
 * 6. Call renderTable(users) to update the view.
 */
function handleSort(event) {
  // ... your implementation here ...
  const th = event.currentTarget;
  const columnIndex = th.cellIndex;
  const currentDir = th.getAttribute('data-sort-dir')||'asc';
  const newDir = currentDir==='asc'?'desc':'asc';

  let property;
  switch(columnIndex){
    case 0: property = 'name'; break;
    case 1: property = 'email'; break;
    case 2: property = 'is_admin'; break;
    default: return;
  }

  const sortedUsers=[...users];

  sortedUsers.sort((a,b) =>{
    let aVal = a[property];
    let bVal = b[property];

    if(property === 'is_admin'){
      aVal=aVal===1?1:0;
      bVal=bVal===1?1:0;
      return newDir === 'asc' ? aVal-bVal:bVal-aVal;
    }else{
      return newDir === 'asc'
      ?String(aVal).localeCompare(String(bVal)) : String(bVal).localeCompare(String(aVal));
    }
  });
  users=sortedUsers;
  th.setAttribute('data-sort-dir',newDir);

  renderTable(users);
}

/**
 * TODO: Implement the loadUsersAndInitialize function.
 * This function must be async.
 * It should:
 * 1. Send a GET request to '../api/index.php' using fetch().
 * 2. Check if the response is ok. If not, log the error and show an alert.
 * 3. Parse the JSON response: await response.json().
 *    The API returns { success: true, data: [ ...users ] }.
 * 4. Assign the data array to the global 'users' variable.
 * 5. Call renderTable(users) to populate the table.
 * 6. Attach all event listeners (only on the first call, or use { once: true } where appropriate):
 *    - "submit" on changePasswordForm  -> handleChangePassword
 *    - "submit" on addUserForm         -> handleAddUser
 *    - "click"  on userTableBody       -> handleTableClick
 *    - "input"  on searchInput         -> handleSearch
 *    - "click"  on each th in tableHeaders -> handleSort
 */
async function loadUsersAndInitialize() {
  // ... your implementation here ...
  try{
    const response = await fetch('../api/index.php');
    if(!response.ok){
      throw new Error('Failed to fetch users');
    }

    const data = await response.json();
    if(data.success && data.data){
      users=data.data;
      renderTable(users);
    }else{
      console.error('Invalid API response:',data);
      alert('Failed to load users. Please refresh the page.');
    }

    if(changePasswordForm){
      changePasswordForm.addEventListener('submit',handleChangePassword);
    }
    if(addUserForm){
      addUserForm.addEventListener('submit',handleAddUser);
    }
    if(userTableBody){
      userTableBody.addEventListener('click',handleTableClick);
    }
    if(searchInput){
      searchInput.addEventListener('input',handleSearch);
    }
    if(tableHeaders){
      tableHeaders.forEach(th=>{
        th.addEventListener('click',handleSort);
      });
    }


  }catch(error){
    console.error('Error loading users:',error);
    alert('Failed to load users. Please check your connection and refresh the page.');
  }
}

// --- Initial Page Load ---
loadUsersAndInitialize();
