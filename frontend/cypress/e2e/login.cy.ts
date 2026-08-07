describe('Login', () => {

  it('TC-F-01: Login exitoso', () => {

    cy.visit('/login')

    cy.get('input[type="email"]').type('admin@leodega.com')
    cy.get('input[type="password"]').type('admin123')

    cy.contains('Iniciar Sesión').click()

    // ⏳ espera la redirección (NO la request)
    cy.location('pathname', { timeout: 200000 })
      .should('eq', '/admin/bodegas')

    // ✅ opcional: token guardado
    cy.window().then((win) => {
      expect(win.localStorage.getItem('auth_token')).to.exist
    })
  })
})


