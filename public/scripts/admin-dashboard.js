document.addEventListener('DOMContentLoaded', () => {
    // Role fields toggle
    const roleSelect = document.querySelector('#role-select');
    const roleSections = document.querySelectorAll('.role-fields');

    const toggleRoleSections = () => {
        const selectedRole = roleSelect.value;
        roleSections.forEach(section => {
            const shouldHide = section.dataset.role !== selectedRole;
            section.classList.toggle('is-hidden', shouldHide);
            section.querySelectorAll('input, select, textarea').forEach(field => {
                field.disabled = shouldHide;
            });
        });
    };

    roleSelect?.addEventListener('change', toggleRoleSections);
    toggleRoleSections();

    // User filtering
    const searchInput = document.querySelector('#user-search');
    const filterRole = document.querySelector('#filter-role');
    const filterStatus = document.querySelector('#filter-status');
    const usersTable = document.querySelector('#users-table');
    const filterResults = document.querySelector('#filter-results');

    const filterUsers = () => {
        if (!usersTable) return;
        
        const searchTerm = searchInput?.value.toLowerCase().trim() || '';
        const selectedRole = filterRole?.value || '';
        const selectedStatus = filterStatus?.value || '';
        
        const rows = usersTable.querySelectorAll('tbody tr');
        let visibleCount = 0;
        const totalCount = rows.length;
        
        rows.forEach(row => {
            const userName = row.querySelector('td:nth-child(1)')?.textContent.toLowerCase() || '';
            const userRole = row.dataset.role || '';
            const userStatus = row.dataset.status || '';
            
            const matchesSearch = !searchTerm || userName.includes(searchTerm);
            const matchesRole = !selectedRole || userRole === selectedRole;
            const matchesStatus = !selectedStatus || userStatus === selectedStatus;
            
            const isVisible = matchesSearch && matchesRole && matchesStatus;
            row.style.display = isVisible ? '' : 'none';
            
            if (isVisible) visibleCount++;
        });
        
        if (filterResults) {
            if (searchTerm || selectedRole || selectedStatus) {
                filterResults.textContent = `Znaleziono: ${visibleCount} z ${totalCount}`;
            } else {
                filterResults.textContent = `Łącznie: ${totalCount}`;
            }
        }
    };

    searchInput?.addEventListener('input', filterUsers);
    filterRole?.addEventListener('change', filterUsers);
    filterStatus?.addEventListener('change', filterUsers);
    
    // Initial count
    filterUsers();
});

