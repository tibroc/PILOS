import { interceptIndefinitely } from "../support/utils/interceptIndefinitely.js";

describe("General", function () {
  beforeEach(function () {
    cy.init();
    cy.interceptRoomIndexRequests();
  });

  it("all locales get rendered", function () {
    cy.visit("/rooms");

    // Open menu to check if the correct locales are shown
    cy.get('[data-test="navbar-locale"]').click();
    cy.get('[data-test="submenu"]')
      .eq(1)
      .within(() => {
        cy.get("li").should("have.length", 3);

        cy.get('[data-test="navbar-locale-de"]')
          .should("exist")
          .should("have.text", "Deutsch");

        cy.get('[data-test="navbar-locale-en"]')
          .should("exist")
          .should("have.text", "English");

        cy.get('[data-test="navbar-locale-fr"]')
          .should("exist")
          .should("have.text", "Français");
      });
  });

  it("hide locale menu if only one locale is available", function () {
    cy.intercept("GET", "/api/v1/locales", {
      statusCode: 200,
      body: ["en"],
    });
    cy.visit("/rooms");

    // Check locale select is not shown
    cy.get('[data-test="navbar-locale"]').should("not.exist");
  });

  it("changing selected locale", function () {
    // Intercept locale and de request
    const localeRequest = interceptIndefinitely(
      "POST",
      "/api/v1/locale",
      {
        statusCode: 200,
      },
      "localeRequest",
    );

    cy.intercept("GET", "/api/v1/locale/de", {
      statusCode: 200,
    }).as("deRequest");

    cy.visit("/rooms");

    cy.wait("@roomRequest");

    cy.get('[data-test="overlay"]').should("not.exist");
    // Open menu and click on a different locale than the current one
    cy.get('[data-test="navbar-locale"]').click();
    cy.get('[data-test="submenu"]')
      .eq(1)
      .should("be.visible")
      .within(() => {
        cy.get('[data-test="navbar-locale-de"]')
          .should("exist")
          .should("have.text", "Deutsch")
          .click();
      });

    // Check loading
    cy.get('[data-test="overlay"]')
      .should("be.visible")
      .then(() => {
        localeRequest.sendResponse();
      });

    // Check that the correct requests are made
    cy.wait("@localeRequest");
    cy.wait("@deRequest");

    // Check that the menu is closed
    cy.get('[data-test="submenu"]').should("not.be.visible");
  });

  it("changing selected locale error", function () {
    // Shows a corresponding error message and does not change the language on 422 error
    cy.intercept("POST", "/api/v1/locale", {
      statusCode: 422,
      body: {
        errors: {
          locale: ["Test"],
        },
      },
    }).as("localeRequest");

    cy.intercept("GET", "/api/v1/locale/de", cy.spy().as("deRequestSpy"));

    cy.visit("/rooms");

    // Open menu and click on a different locale than the current one
    cy.get('[data-test="navbar-locale"]').click();
    cy.get('[data-test="submenu"]')
      .eq(1)
      .within(() => {
        cy.get('[data-test="navbar-locale-de"]')
          .should("exist")
          .should("have.text", "Deutsch")
          .click();
      });

    // Check that the locale request was made
    cy.wait("@localeRequest");
    // Check that the request for the new language was not send (language stays the same after error)
    cy.get("@deRequestSpy").should("not.be.called");

    // Check if error message is shown
    cy.checkToastMessage("Test");

    // Test other errors
    cy.intercept("POST", "/api/v1/locale", {
      statusCode: 500,
      body: {
        message: ["Test"],
      },
    }).as("localeRequest");

    cy.intercept("GET", "/api/v1/locale/de", cy.spy().as("deRequestSpy"));

    // Open menu and click on a different locale than the current one
    cy.get('[data-test="navbar-locale"]').click();
    cy.get('[data-test="submenu"]')
      .eq(1)
      .within(() => {
        cy.get('[data-test="navbar-locale-de"]')
          .should("exist")
          .should("have.text", "Deutsch")
          .click();
      });

    // Check that the locale request was made
    cy.wait("@localeRequest");
    // Check that the request for the new language was not send (language stays the same after error)
    cy.get("@deRequestSpy").should("not.be.called");

    // Check if error message is shown
    cy.checkToastMessage([
      'app.flash.server_error.message_{"message":["Test"]}',
      'app.flash.server_error.error_code_{"statusCode":500}',
    ]);
  });

  it("disabled welcome page redirect unauthenticated users to login", function () {
    cy.intercept("GET", "api/v1/currentUser", {});
    cy.fixture("config.json").then((config) => {
      config.data.general.no_welcome_page = true;
      config.data.auth.local = true;

      cy.intercept("GET", "api/v1/config", {
        statusCode: 200,
        body: config,
      });
    });

    // Visit the root page
    cy.visit("/");

    // Should be redirected to rooms overview, but since the user is not authenticated, should be redirected to login page
    cy.url().should("contain", "/login?redirect=/rooms");

    cy.get('[data-test="login-tab-local"]').should("be.visible");
  });

  it("disabled welcome page redirect authenticated users to rooms overview", function () {
    cy.fixture("config.json").then((config) => {
      config.data.general.no_welcome_page = true;

      cy.intercept("GET", "api/v1/config", {
        statusCode: 200,
        body: config,
      });
    });

    // Visit the root page
    cy.visit("/");

    // Should be redirected to rooms overview
    cy.url().should("contain", "/rooms");
  });

  it("welcome page shown", function () {
    // Visit the root page
    cy.visit("/");

    // Check if the welcome page is shown
    cy.get("h1").should("be.visible").and("contain", "home.title");
  });

  it("check help button if help url specified", function () {
    cy.fixture("config.json").then((config) => {
      config.data.general.help_url = "https://example.org/?foo=a&bar=b";

      cy.intercept("GET", "/api/v1/config", config).as("configRequest");
    });
    cy.visit("/");

    cy.get('[data-test="navbar-help"]')
      .should("be.visible")
      .should("have.attr", "href", "https://example.org/?foo=a&bar=b")
      .and("have.attr", "target", "_blank")
      .invoke("removeAttr", "target");

    cy.get('[data-test="navbar-help"]').click();

    // Check that redirect worked
    cy.origin("https://example.org", () => {
      cy.url().should("eq", "https://example.org/?foo=a&bar=b");
    });
  });

  it("check help button hidden if help url not specified", function () {
    cy.fixture("config.json").then((config) => {
      config.data.general.help_url = "";

      cy.intercept("GET", "/api/v1/config", config).as("configRequest");
    });
    cy.visit("/");

    cy.get('[data-test="navbar-help"]').should("not.exist");
  });

  it("toggle dark mode", function () {
    cy.visit("/");

    // Check if light mode is enabled by default
    cy.get("html").should("not.have.class", "dark");
    cy.get('[data-test="navbar-dark-mode"]')
      .find("i")
      .should("have.class", "fa-sun");

    // Toggle dark mode
    cy.get('[data-test="navbar-dark-mode"]').click();

    // Check if dark mode is enabled
    cy.get("html").should("have.class", "dark");
    cy.get('[data-test="navbar-dark-mode"]')
      .find("i")
      .should("have.class", "fa-moon");

    // Toggle dark mode again
    cy.get('[data-test="navbar-dark-mode"]').click();

    // Check if light mode is enabled again
    cy.get("html").should("not.have.class", "dark");
    cy.get('[data-test="navbar-dark-mode"]')
      .find("i")
      .should("have.class", "fa-sun");
  });
});
