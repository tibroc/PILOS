import { interceptIndefinitely } from "../support/utils/interceptIndefinitely.js";

describe("Rooms index create new room", function () {
  beforeEach(function () {
    cy.init();
    cy.interceptRoomIndexRequests();
    cy.fixture("currentUser.json").then((currentUser) => {
      currentUser.data.permissions = ["rooms.create"];
      cy.intercept("GET", "api/v1/currentUser", {
        statusCode: 200,
        body: currentUser,
      });
    });
  });

  it("button to create new room hidden", function () {
    cy.intercept("GET", "api/v1/currentUser", { fixture: "currentUser.json" });

    cy.visit("/rooms");

    cy.wait("@roomRequest");
    // Check that room create button is hidden for user that does not have the permission to create rooms
    cy.get('[data-test="room-create-button"]').should("not.exist");
    // Check that room limit tag does not exist
    cy.contains("rooms.room_limit").should("not.exist");
  });

  it("create new room", function () {
    // Intercept room view requests (needed for redirect after room creation)
    cy.interceptRoomViewRequests();

    const createRoomRequest = interceptIndefinitely(
      "POST",
      "api/v1/rooms",
      {
        statusCode: 201,
        body: {
          data: {
            id: "abc-def-123",
            owner: {
              id: 1,
              name: "John Doe",
            },
            type: {
              id: 2,
              name: "Meeting",
              color: "#4a5c66",
            },
          },
        },
      },
      "createRoomRequest",
    );

    cy.visit("/rooms");

    cy.wait("@roomRequest");

    // Check that room create modal is hidden
    cy.get('[data-test="room-create-dialog"]').should("not.exist");
    // Check that room limit tag does not exist
    cy.contains("rooms.room_limit").should("not.exist");

    cy.intercept("GET", "api/v1/roomTypes*", {
      fixture: "roomTypesWithSettings.json",
    });

    // Open room create modal
    cy.get('[data-test="room-create-button"]')
      .should("have.text", "rooms.create.title")
      .click();

    cy.get('[data-test="room-create-dialog"]')
      .should("be.visible")
      .within(() => {
        // Check that room type details does not exist (no room type selected)
        cy.get('[data-test="room-type-details"]').should("not.exist");

        cy.contains("rooms.create.title").should("be.visible");
        cy.get("#room-name").should("have.value", "").type("New Room");
        // Check that the room types are shown correctly
        cy.get('[data-test="room-type-select-option"]').should(
          "have.length",
          4,
        );

        cy.get('[data-test="room-type-select-option"]')
          .eq(0)
          .should("have.text", "Lecture");
        cy.get('[data-test="room-type-select-option"]')
          .eq(1)
          .should("have.text", "Meeting");
        cy.get('[data-test="room-type-select-option"]')
          .eq(2)
          .should("have.text", "Exam");
        cy.get('[data-test="room-type-select-option"]')
          .eq(3)
          .should("have.text", "Seminar");

        // Select a room type
        cy.get('[data-test="room-type-select-option"]').eq(1).click();

        // Check that room type details are shown correctly
        cy.get('[data-test="room-type-details"]')
          .should("be.visible")
          .within(() => {
            cy.contains("admin.room_types.missing_description").should(
              "be.visible",
            );
            // Check that default room settings are hidden
            cy.contains("rooms.settings.general.title").should(
              "not.be.visible",
            );

            // Open default settings
            cy.get('[data-test="show-default-settings-button"]')
              .should(
                "have.text",
                "admin.room_types.default_room_settings.title",
              )
              .click();
            // Check that default room settings are shown correctly
            cy.contains("rooms.settings.general.title").should("be.visible");

            cy.get('[data-test="room-type-has_access_code-setting"]').should(
              "include.text",
              "rooms.settings.general.has_access_code",
            );
            cy.checkDefaultRoomSettingField(
              "has_access_code",
              true,
              false,
              false,
            );

            cy.get('[data-test="room-type-allow_guests-setting"]').should(
              "include.text",
              "rooms.settings.general.allow_guests",
            );
            cy.checkDefaultRoomSettingField("allow_guests", true, true, false);

            // Check that other settings exist and are shown correctly
            cy.contains("rooms.settings.video_conference.title");

            cy.get('[data-test="room-type-everyone_can_start-setting"]').should(
              "include.text",
              "rooms.settings.video_conference.everyone_can_start",
            );
            cy.checkDefaultRoomSettingField(
              "everyone_can_start",
              false,
              false,
              false,
            );

            cy.get('[data-test="room-type-mute_on_start-setting"]').should(
              "include.text",
              "rooms.settings.video_conference.mute_on_start",
            );
            cy.checkDefaultRoomSettingField("mute_on_start", true, true, false);

            cy.get('[data-test="room-type-lobby-setting"]').should(
              "include.text",
              "rooms.settings.video_conference.lobby.title",
            );
            cy.checkDefaultRoomSettingField(
              "lobby",
              "rooms.settings.video_conference.lobby.only_for_guests_enabled",
              true,
              true,
            );

            cy.contains("rooms.settings.recordings.title");

            cy.get('[data-test="room-type-record_attendance-setting"]').should(
              "include.text",
              "rooms.settings.recordings.record_attendance",
            );
            cy.checkDefaultRoomSettingField(
              "record_attendance",
              false,
              false,
              false,
            );

            cy.get('[data-test="room-type-record-setting"]').should(
              "include.text",
              "rooms.settings.recordings.record_video_conference",
            );
            cy.checkDefaultRoomSettingField("record", false, false, false);

            cy.get(
              '[data-test="room-type-auto_start_recording-setting"]',
            ).should(
              "include.text",
              "rooms.settings.recordings.auto_start_recording",
            );
            cy.checkDefaultRoomSettingField(
              "auto_start_recording",
              false,
              false,
              false,
            );

            cy.get(
              '[data-test="room-type-lock_settings_disable_cam-setting"]',
            ).should(
              "include.text",
              "rooms.settings.restrictions.lock_settings_disable_cam",
            );
            cy.checkDefaultRoomSettingField(
              "lock_settings_disable_cam",
              false,
              false,
              false,
            );

            cy.get(
              '[data-test="room-type-webcams_only_for_moderator-setting"]',
            ).should(
              "include.text",
              "rooms.settings.restrictions.webcams_only_for_moderator",
            );
            cy.checkDefaultRoomSettingField(
              "webcams_only_for_moderator",
              true,
              false,
              false,
            );

            cy.get(
              '[data-test="room-type-lock_settings_disable_mic-setting"]',
            ).should(
              "include.text",
              "rooms.settings.restrictions.lock_settings_disable_mic",
            );
            cy.checkDefaultRoomSettingField(
              "lock_settings_disable_mic",
              false,
              true,
              false,
            );

            cy.get(
              '[data-test="room-type-lock_settings_disable_public_chat-setting"]',
            ).should(
              "include.text",
              "rooms.settings.restrictions.lock_settings_disable_public_chat",
            );
            cy.checkDefaultRoomSettingField(
              "lock_settings_disable_public_chat",
              true,
              false,
              false,
            );

            cy.get(
              '[data-test="room-type-lock_settings_disable_private_chat-setting"]',
            ).should(
              "include.text",
              "rooms.settings.restrictions.lock_settings_disable_private_chat",
            );
            cy.checkDefaultRoomSettingField(
              "lock_settings_disable_private_chat",
              false,
              false,
              false,
            );

            cy.get(
              '[data-test="room-type-lock_settings_disable_note-setting"]',
            ).should(
              "include.text",
              "rooms.settings.restrictions.lock_settings_disable_note",
            );
            cy.checkDefaultRoomSettingField(
              "lock_settings_disable_note",
              true,
              true,
              false,
            );

            cy.get(
              '[data-test="room-type-lock_settings_hide_user_list-setting"]',
            ).should(
              "include.text",
              "rooms.settings.restrictions.lock_settings_hide_user_list",
            );
            cy.checkDefaultRoomSettingField(
              "lock_settings_hide_user_list",
              true,
              false,
              false,
            );

            cy.contains("rooms.settings.participants.title");

            cy.get('[data-test="room-type-allow_membership-setting"]').should(
              "include.text",
              "rooms.settings.participants.allow_membership",
            );
            cy.checkDefaultRoomSettingField(
              "allow_membership",
              false,
              true,
              false,
            );

            cy.get('[data-test="room-type-default_role-setting"]').should(
              "include.text",
              "rooms.settings.participants.default_role.title",
            );
            cy.checkDefaultRoomSettingField(
              "default_role",
              "rooms.roles.participant",
              false,
              true,
            );

            cy.contains("rooms.settings.advanced.title");

            cy.get('[data-test="room-type-visibility-setting"]').should(
              "include.text",
              "rooms.settings.advanced.visibility.title",
            );
            cy.checkDefaultRoomSettingField(
              "visibility",
              "rooms.settings.advanced.visibility.public",
              false,
              true,
            );
          });

        // Create new room
        cy.get('[data-test="dialog-save-button"]').click();

        // Check loading
        cy.get("#room-name").should("be.disabled");
        cy.get(".p-listbox-list").should("have.attr", "aria-disabled", "true");
        cy.get('[data-test="dialog-save-button"]')
          .should("be.disabled")
          .then(() => {
            createRoomRequest.sendResponse();
          });
      });

    // Check if correct request is send
    cy.wait("@createRoomRequest").then((interception) => {
      expect(interception.request.body).to.eql({
        name: "New Room",
        room_type: 2,
      });
    });

    // Check if redirected to the created room
    cy.url().should("include", "/rooms/abc-def-123");
  });

  it("create new room with features enabled", function () {
    // Enable streaming
    cy.fixture("config.json").then((config) => {
      config.data.streaming.enabled = true;

      cy.intercept("GET", "api/v1/config", {
        statusCode: 200,
        body: config,
      });
    });

    // Intercept room view requests (needed for redirect after room creation)
    cy.interceptRoomViewRequests();

    cy.intercept("POST", "api/v1/rooms", {
      statusCode: 201,
      body: {
        data: {
          id: "abc-def-123",
          owner: {
            id: 1,
            name: "John Doe",
          },
          type: {
            id: 2,
            name: "Meeting",
            color: "#4a5c66",
          },
        },
      },
    }).as("createRoomRequest");

    cy.visit("/rooms");

    cy.wait("@roomRequest");

    // Check that room create modal is hidden
    cy.get('[data-test="room-create-dialog"]').should("not.exist");
    // Check that room limit tag does not exist
    cy.contains("rooms.room_limit").should("not.exist");

    cy.fixture("roomTypesWithSettings.json").then((config) => {
      config.data[0].features.streaming.enabled = true;

      cy.intercept("GET", "api/v1/roomTypes*", {
        statusCode: 200,
        body: config,
      });
    });

    // Open room create modal
    cy.get('[data-test="room-create-button"]')
      .should("have.text", "rooms.create.title")
      .click();

    cy.get('[data-test="room-create-dialog"]')
      .should("be.visible")
      .within(() => {
        // Check that room type details does not exist (no room type selected)
        cy.get('[data-test="room-type-details"]').should("not.exist");

        // Select a room type
        cy.get('[data-test="room-type-select-option"]').eq(0).click();

        // Check that room type details are shown correctly
        cy.get('[data-test="room-type-details"]')
          .should("be.visible")
          .within(() => {
            // Check features section is shown
            cy.get('[data-test="room-type-features"]')
              .should("be.visible")
              .should("contain.text", "rooms.room_types.features.title");

            // Check that the streaming state of the room type is shown correctly
            cy.get('[data-test="room-type-feature-streaming"]').should(
              "have.class",
              "p-tag-success",
            );
          });

        // Select other room type
        cy.get('[data-test="room-type-select-option"]').eq(1).click();
        // Check that room type details are shown correctly
        cy.get('[data-test="room-type-details"]')
          .should("be.visible")
          .within(() => {
            // Check features section is shown
            cy.get('[data-test="room-type-features"]')
              .should("be.visible")
              .should("contain.text", "rooms.room_types.features.title");

            // Check that the streaming state of the room type is shown correctly
            cy.get('[data-test="room-type-feature-streaming"]').should(
              "have.class",
              "p-tag-secondary",
            );
          });
      });
  });

  it("create new room with features disabled", function () {
    // Disable streaming
    cy.fixture("config.json").then((config) => {
      config.data.streaming.enabled = false;

      cy.intercept("GET", "api/v1/config", {
        statusCode: 200,
        body: config,
      });
    });

    // Intercept room view requests (needed for redirect after room creation)
    cy.interceptRoomViewRequests();

    cy.intercept("POST", "api/v1/rooms", {
      statusCode: 201,
      body: {
        data: {
          id: "abc-def-123",
          owner: {
            id: 1,
            name: "John Doe",
          },
          type: {
            id: 2,
            name: "Meeting",
            color: "#4a5c66",
          },
        },
      },
    }).as("createRoomRequest");

    cy.visit("/rooms");

    cy.wait("@roomRequest");

    // Check that room create modal is hidden
    cy.get('[data-test="room-create-dialog"]').should("not.exist");
    // Check that room limit tag does not exist
    cy.contains("rooms.room_limit").should("not.exist");

    cy.fixture("roomTypesWithSettings.json").then((config) => {
      config.data[0].features.streaming.enabled = true;

      cy.intercept("GET", "api/v1/roomTypes*", {
        statusCode: 200,
        body: config,
      });
    });

    // Open room create modal
    cy.get('[data-test="room-create-button"]')
      .should("have.text", "rooms.create.title")
      .click();

    cy.get('[data-test="room-create-dialog"]')
      .should("be.visible")
      .within(() => {
        // Select a room type
        cy.get('[data-test="room-type-select-option"]').eq(0).click();

        // Check that room type details are shown correctly
        cy.get('[data-test="room-type-details"]')
          .should("be.visible")
          .within(() => {
            // Check features section is hidden
            cy.get('[data-test="room-type-features"]').should("not.exist");
          });
      });
  });

  it("create new room with features disabled and show disabled features", function () {
    // Disable streaming, show disabled features
    cy.fixture("config.json").then((config) => {
      config.data.general.hide_disabled_features = false;
      config.data.streaming.enabled = false;

      cy.intercept("GET", "api/v1/config", {
        statusCode: 200,
        body: config,
      });
    });

    // Intercept room view requests (needed for redirect after room creation)
    cy.interceptRoomViewRequests();

    cy.intercept("POST", "api/v1/rooms", {
      statusCode: 201,
      body: {
        data: {
          id: "abc-def-123",
          owner: {
            id: 1,
            name: "John Doe",
          },
          type: {
            id: 2,
            name: "Meeting",
            color: "#4a5c66",
          },
        },
      },
    }).as("createRoomRequest");

    cy.visit("/rooms");

    cy.wait("@roomRequest");

    // Check that room create modal is hidden
    cy.get('[data-test="room-create-dialog"]').should("not.exist");
    // Check that room limit tag does not exist
    cy.contains("rooms.room_limit").should("not.exist");

    cy.fixture("roomTypesWithSettings.json").then((config) => {
      config.data[0].features.streaming.enabled = true;

      cy.intercept("GET", "api/v1/roomTypes*", {
        statusCode: 200,
        body: config,
      });
    });

    // Open room create modal
    cy.get('[data-test="room-create-button"]')
      .should("have.text", "rooms.create.title")
      .click();

    cy.get('[data-test="room-create-dialog"]')
      .should("be.visible")
      .within(() => {
        // Check that room type details does not exist (no room type selected)
        cy.get('[data-test="room-type-details"]').should("not.exist");

        // Select a room type
        cy.get('[data-test="room-type-select-option"]').eq(0).click();

        // Check that room type details are shown correctly
        cy.get('[data-test="room-type-details"]')
          .should("be.visible")
          .within(() => {
            // Check features section is shown
            cy.get('[data-test="room-type-features"]')
              .should("be.visible")
              .should("contain.text", "rooms.room_types.features.title");

            // Check that the streaming state of the room type is shown correctly
            cy.get('[data-test="room-type-feature-streaming"]').should(
              "have.class",
              "p-tag-secondary",
            );
          });
      });
  });

  it("create new room errors", function () {
    // Create new room without room type
    cy.intercept("POST", "api/v1/rooms", {
      statusCode: 422,
      body: {
        message: "The given data was invalid",
        errors: {
          room_type: ["The Room type field is required."],
        },
      },
    }).as("createRoomRequest");

    cy.fixture("roomTypes.json").then((roomTypes) => {
      const invalidType = JSON.parse(JSON.stringify(roomTypes.data[0]));
      invalidType.id = 999;
      invalidType.name = "Invalid Type";
      roomTypes.data.unshift(invalidType);

      cy.intercept("GET", "api/v1/roomTypes*", {
        statusCode: 200,
        body: roomTypes,
      }).as("roomTypeRequest");
    });

    cy.visit("/rooms");

    // Open room create modal
    cy.get('[data-test="room-create-button"]')
      .should("have.text", "rooms.create.title")
      .click();

    cy.get('[data-test="room-create-dialog"]')
      .should("be.visible")
      .within(() => {
        // Enter room name
        cy.get("#room-name").should("have.value", "").type("New Room");

        // Wait for room types to be loaded
        cy.wait("@roomTypeRequest");
        // Add spy to check if room types are reloaded on form errors
        cy.intercept(
          "GET",
          "api/v1/roomTypes*",
          cy.spy().as("roomTypeRequestSpy"),
        );

        // Create new room
        cy.get('[data-test="dialog-save-button"]').click();
      });

    cy.wait("@createRoomRequest");

    // Check that error gets displayed
    cy.get('[data-test="room-create-dialog"]')
      .should("be.visible")
      .and("include.text", "The Room type field is required.");

    // Check if room types list is not reloaded
    cy.get("@roomTypeRequestSpy").should("not.be.called");

    // Create new room with invalid room type
    cy.intercept("POST", "api/v1/rooms", {
      statusCode: 422,
      body: {
        message: "The selected Room type is invalid.",
        errors: {
          room_type: ["The selected Room type is invalid."],
        },
      },
    }).as("createRoomRequest");

    cy.intercept("GET", "api/v1/roomTypes*", { fixture: "roomTypes.json" }).as(
      "roomTypeRequest",
    );

    cy.get('[data-test="room-create-dialog"]')
      .should("be.visible")
      .within(() => {
        // Select a room type
        cy.get('[data-test="room-type-select-option"]').eq(0).click();
        // Check that room type is selected
        cy.get('[data-test="room-type-details"] > h3').contains("Invalid Type");

        // Create new room
        cy.get('[data-test="dialog-save-button"]').click();
      });

    cy.wait("@createRoomRequest");
    cy.wait("@roomTypeRequest");

    // Check that new error gets displayed and old error is not shown
    cy.get('[data-test="room-create-dialog"]')
      .should("be.visible")
      .and("include.text", "The selected Room type is invalid.")
      .and("not.include.text", "The Room type field is required.");

    // Check refreshed list is shown and room type select is reset
    cy.get('[data-test="room-type-select-option"]').should("have.length", 4);
    // Check that no room type is selected
    cy.get('[data-test="room-type-details"]').should("not.exist");

    // Create new room without name
    cy.intercept("POST", "api/v1/rooms", {
      statusCode: 422,
      body: {
        message: "The given data was invalid",
        errors: {
          name: ["The Name field is required."],
        },
      },
    }).as("createRoomRequest");

    cy.get('[data-test="room-create-dialog"]')
      .should("be.visible")
      .within(() => {
        // Remove text from name input
        cy.get("#room-name").should("have.value", "New Room").clear();
        // Select a room type
        cy.get('[data-test="room-type-select-option"]').eq(0).click();

        // Create new room
        cy.get('[data-test="dialog-save-button"]').click();
      });

    cy.wait("@createRoomRequest");

    // Check that error is shown and previous errors are not shown
    cy.get('[data-test="room-create-dialog"]')
      .should("be.visible")
      .and("include.text", "The Name field is required.")
      .and("not.include.text", "The selected Room type is invalid.");

    // Check with other error (500)
    cy.intercept("POST", "api/v1/rooms", {
      statusCode: 500,
      body: {
        message: "Test",
      },
    }).as("createRoomRequest");

    cy.get('[data-test="room-create-dialog"]')
      .should("be.visible")
      .within(() => {
        // Enter room name
        cy.get("#room-name").should("have.value", "").type("New Room");

        // Select a room type
        cy.get('[data-test="room-type-select-option"]').eq(0).click();

        // Create new room
        cy.get('[data-test="dialog-save-button"]').click();
      });

    cy.wait("@createRoomRequest");

    // Check if error message is shown
    cy.checkToastMessage([
      'app.flash.server_error.message_{"message":"Test"}',
      'app.flash.server_error.error_code_{"statusCode":500}',
    ]);

    // Check that 422 error messages are hidden
    cy.get('[data-test="room-create-dialog"]')
      .should("be.visible")
      .and("not.include.text", "The Name field is required.");

    // Create new room forbidden
    cy.intercept("POST", "api/v1/rooms", {
      statusCode: 403,
    }).as("createRoomRequest");

    cy.intercept("GET", "api/v1/currentUser", {
      fixture: "currentUser.json",
    }).as("currentUserRequest");

    cy.get('[data-test="room-create-dialog"]')
      .should("be.visible")
      .within(() => {
        // Create new room
        cy.get('[data-test="dialog-save-button"]').click();
      });

    cy.wait("@createRoomRequest");
    cy.wait("@currentUserRequest");

    // Check that create room dialog is closed
    cy.get('[data-test="room-create-dialog"]').should("not.exist");

    // Check that create room button is disabled
    cy.get('[data-test="room-create-button"]').should("not.exist");

    // Check if error message is visible
    cy.checkToastMessage("rooms.flash.no_new_room");

    // Reload page to check other errors
    cy.fixture("currentUser.json").then((currentUser) => {
      currentUser.data.permissions = ["rooms.create"];
      cy.intercept("GET", "api/v1/currentUser", {
        statusCode: 200,
        body: currentUser,
      });
    });

    cy.reload();

    // Test with 401 error
    cy.intercept("POST", "api/v1/rooms", {
      statusCode: 401,
    }).as("createRoomRequest");

    cy.get('[data-test="room-create-button"]').click();

    cy.get('[data-test="room-create-dialog"]')
      .should("be.visible")
      .within(() => {
        // Enter room name
        cy.get("#room-name").should("have.value", "").type("New Room");

        // Select a room type
        cy.get('[data-test="room-type-select-option"]').eq(0).click();

        // Create new room
        cy.get('[data-test="dialog-save-button"]').click();
      });

    cy.wait("@createRoomRequest");

    // Check that redirect worked and error message is shown
    cy.url().should("include", "/login?redirect=/rooms");

    cy.checkToastMessage("app.flash.unauthenticated");
  });

  it("create new room limit reached", function () {
    cy.fixture("rooms.json").then((rooms) => {
      rooms.data = [];
      rooms.meta.total = 0;
      rooms.meta.total_no_filter = 0;
      rooms.meta.total_own = 0;

      cy.intercept("GET", "api/v1/rooms?*", {
        statusCode: 200,
        body: rooms,
      }).as("roomRequest");
    });

    cy.fixture("currentUser.json").then((currentUser) => {
      currentUser.data.permissions = ["rooms.create"];
      currentUser.data.room_limit = 1;
      cy.intercept("GET", "api/v1/currentUser", {
        statusCode: 200,
        body: currentUser,
      }).as("currentUserRequest");
    });

    cy.intercept("POST", "api/v1/rooms", {
      statusCode: 463,
      body: {
        message: "Test",
      },
    }).as("createRoomRequest");

    cy.visit("/rooms");

    cy.wait("@roomRequest");

    // Check if room limit is shown
    cy.contains('rooms.room_limit_{"has":0,"max":1}').should("be.visible");

    // Open room create modal
    cy.get('[data-test="room-create-button"]')
      .should("have.text", "rooms.create.title")
      .click();
    cy.get('[data-test="room-create-dialog"]')
      .should("be.visible")
      .within(() => {
        // Enter room name
        cy.get("#room-name").should("have.text", "").type("New Room");

        // Select a room type
        cy.get('[data-test="room-type-select-option"]').eq(0).click();

        // Change response so that the room limit gets reached
        cy.fixture("rooms.json").then((rooms) => {
          rooms.data = rooms.data.slice(0, 1);
          rooms.meta.last_page = 3;
          rooms.meta.per_page = 1;
          rooms.meta.to = 1;

          cy.intercept("GET", "api/v1/rooms?*", {
            statusCode: 200,
            body: rooms,
          }).as("roomRequest");
        });

        // Create new room
        cy.get('[data-test="dialog-save-button"]').click();
      });

    cy.wait("@createRoomRequest");
    cy.wait("@currentUserRequest");
    cy.wait("@roomRequest");

    cy.get('[data-test="room-create-dialog"]').should("not.exist");

    // Check if error message is visible
    cy.checkToastMessage(["Test"]);

    // Check if room limit is updated and create button is disabled
    cy.get('[data-test="room-create-button"]').should("be.disabled");
    cy.contains('rooms.room_limit_{"has":1,"max":1}').should("be.visible");

    // Switch to next page
    cy.fixture("rooms.json").then((rooms) => {
      rooms.data = rooms.data.slice(1, 2);
      rooms.meta.current_page = 2;
      rooms.meta.from = 2;
      rooms.meta.last_page = 3;
      rooms.meta.per_page = 1;
      rooms.meta.to = 2;

      cy.intercept("GET", "api/v1/rooms?*", {
        statusCode: 200,
        body: rooms,
      }).as("roomRequest");
    });

    // Click on button for next page (eq(1) needed because there are two paginator components
    // (first one for small devices second one for larger devices))
    cy.get('[data-test="paginator-next-button"]').eq(1).click();

    cy.wait("@roomRequest");

    // Make sure that room limit and button stay the same
    cy.get('[data-test="room-create-button"]').should("be.disabled");
    cy.contains('rooms.room_limit_{"has":1,"max":1}').should("be.visible");

    // Check if room count is not based on items on the current page or the total results,
    // but all rooms of the user, independent of the search query
    cy.fixture("rooms.json").then((rooms) => {
      rooms.data = [];
      rooms.meta.total = 0;
      rooms.meta.total_no_filter = 0;

      cy.intercept("GET", "api/v1/rooms?*", {
        statusCode: 200,
        body: rooms,
      }).as("roomRequest");
    });

    cy.get('[data-test="room-search"] > input').type("Test");
    cy.get('[data-test="room-search"] > input').type("{enter}");

    // Check if room limit is updated and create button is disabled
    cy.get('[data-test="room-create-button"]').should("be.disabled");
    cy.contains('rooms.room_limit_{"has":1,"max":1}').should("be.visible");
  });

  it("create new room limit reached when visiting", function () {
    cy.fixture("rooms.json").then((rooms) => {
      rooms.data = [];
      rooms.meta.total = 0;
      rooms.meta.total_no_filter = 0;

      cy.intercept("GET", "api/v1/rooms?*", {
        statusCode: 200,
        body: rooms,
      }).as("roomRequest");
    });

    cy.fixture("currentUser.json").then((currentUser) => {
      currentUser.data.permissions = ["rooms.create"];
      currentUser.data.room_limit = 1;
      cy.intercept("GET", "api/v1/currentUser", {
        statusCode: 200,
        body: currentUser,
      });
    });

    cy.visit("/rooms");

    cy.wait("@roomRequest");

    // Check if room limit is shown and create button is disabled
    cy.get('[data-test="room-create-button"]').should("be.disabled");
    cy.contains('rooms.room_limit_{"has":1,"max":1}').should("be.visible");
  });

  it("cancel create new room", function () {
    cy.visit("/rooms");

    cy.wait("@roomRequest");

    // Check that room create modal is hidden
    cy.get('[data-test="room-create-dialog"]').should("not.exist");
    // Check that room limit tag does not exist
    cy.contains("rooms.room_limit").should("not.exist");
    // Open room create modal
    cy.get('[data-test="room-create-button"]')
      .should("have.text", "rooms.create.title")
      .click();

    cy.get('[data-test="room-create-dialog"]')
      .should("be.visible")
      .within(() => {
        // Check that room type details does not exist (no room type selected)
        cy.get('[data-test="room-type-details"]').should("not.exist");

        cy.get("#room-name").should("have.value", "").type("New Room");
        // Select a room type
        cy.get('[data-test="room-type-select-option"]').eq(0).click();

        cy.get('[data-test="room-type-details"]').should("be.visible");
      });

    // Cancel room creation
    cy.get('[data-test="dialog-cancel-button"]').click();

    // Check that room create modal is hidden
    cy.get('[data-test="room-create-dialog"]').should("not.exist");

    // Reopen room create modal
    cy.get('[data-test="room-create-button"]')
      .should("have.text", "rooms.create.title")
      .click();

    cy.get('[data-test="room-create-dialog"]')
      .should("be.visible")
      .within(() => {
        // Check that selected values were reset
        cy.get("#room-name").should("have.value", "");
        cy.get('[data-test="room-type-details"]').should("not.exist");
      });
  });

  it("errors loading room types", function () {
    cy.visit("/rooms");

    cy.wait("@roomRequest");

    // Check that room create modal is hidden
    cy.get('[data-test="room-create-dialog"]').should("not.exist");

    // Check with 500 error
    const roomTypesRequest = interceptIndefinitely(
      "GET",
      "api/v1/roomTypes*",
      {
        statusCode: 500,
        body: {
          message: "Test",
        },
      },
      "roomTypesRequest",
    );

    // Open room create modal
    cy.get('[data-test="room-create-button"]').click();

    // Check that dialog loading is shown correctly
    cy.get('[data-test="room-create-dialog"]')
      .should("be.visible")
      .and("include.text", "rooms.create.title")
      .within(() => {
        cy.get('[data-test="overlay"]').should("be.visible");
        cy.get('[data-test="dialog-cancel-button"]')
          .should("be.visible")
          .and("be.disabled");
        cy.get('[data-test="dialog-save-button"]')
          .should("be.visible")
          .and("be.disabled")
          .then(() => {
            roomTypesRequest.sendResponse();
          });
      });

    cy.wait("@roomTypesRequest");

    // Check that error message is shown
    cy.checkToastMessage([
      'app.flash.server_error.message_{"message":"Test"}',
      'app.flash.server_error.error_code_{"statusCode":500}',
    ]);

    // Check that dialog is shown correctly
    cy.get('[data-test="room-create-dialog"]')
      .should("be.visible")
      .within(() => {
        cy.get("#room-name").should("be.visible").and("not.be.disabled");

        cy.get('[data-test="overlay"]').should("not.exist");
        cy.get('[data-test="dialog-cancel-button"]')
          .should("be.visible")
          .and("not.be.disabled");
        cy.get('[data-test="dialog-save-button"]')
          .should("be.visible")
          .and("be.disabled");

        // Reload with valid room types
        cy.intercept("GET", "api/v1/roomTypes*", {
          fixture: "roomTypesWithSettings.json",
        }).as("roomTypesRequest");
        cy.get('[data-test="reload-room-types-button"]')
          .should("be.visible")
          .click();

        cy.wait("@roomTypesRequest");

        // Check that dialog is shown correctly
        // Check that the room types are shown correctly
        cy.get('[data-test="room-type-select-option"]').should(
          "have.length",
          4,
        );

        cy.get('[data-test="room-type-select-option"]')
          .eq(0)
          .should("have.text", "Lecture");
        cy.get('[data-test="room-type-select-option"]')
          .eq(1)
          .should("have.text", "Meeting");
        cy.get('[data-test="room-type-select-option"]')
          .eq(2)
          .should("have.text", "Exam");
        cy.get('[data-test="room-type-select-option"]')
          .eq(3)
          .should("have.text", "Seminar");
      });

    // Close dialog and check with 401 error
    cy.get('[data-test="dialog-cancel-button"]').click();

    cy.intercept("GET", "api/v1/roomTypes*", {
      statusCode: 401,
    }).as("roomTypesRequest");

    cy.get('[data-test="room-create-button"]').click();

    // Check that redirect worked and error message is shown
    cy.url().should("include", "/login?redirect=/rooms");

    cy.checkToastMessage("app.flash.unauthenticated");
  });
});
