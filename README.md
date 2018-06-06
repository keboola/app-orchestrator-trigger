# Orchestrator Trigger

[![Build Status](https://travis-ci.com/keboola/app-orchestrator-trigger.svg?branch=master)](https://travis-ci.com/keboola/app-orchestrator-trigger)

This application allows run orchestration across different Keboola Connection projects.

**Trigger has two modes:**
1) Only trigger external orchestration. Application ends when a new orchestration job is enqueued.
2) Wait until finish mode. Application waits until enqueued job is finished and check job for _success_ status.

# Usage

First, create a new token in the KBC project with your orchestration. The token should have granted access for Orchestrator configurations.
Setup an comprehensible description for the token, like _"Orchestration Trigger - Orchestration name"_ etc.

```
curl -X POST \
     --header "Content-Type: application/x-www-form-urlencoded" \
     --header "X-StorageApi-Token: **STORAGE_API_TOKEN**" \
     --data-binary "description=**NEW_TOKEN_DESCRIPTION**&componentAccess[]=orchestrator" \
'https://connection.keboola.com/v2/storage/tokens'
```

Then you can setup the Orchestrator Trigger app.

### Configuration options

- `#kbcToken` - KBC Storage API token
- `kbcUrl` - KBC Storage API endpoint
    - https://connection.keboola.com for `US` stack
    - https://connection.eu-central-1.keboola.com for `EU` stack
- `orchestrationId` _(integer)_ - ID of triggered orchestration
- `waitUntilFinish` _(boolean, default false false)_ - Wait for job finish and check jobs status



https://connection.keboola.com/admin/projects/{PROJECT_ID}/applications/keboola.app-orchestrator-trigger


## Development

- Clone this repository:

```
git clone https://github.com/keboola/app-orchestrator-trigger.git
cd app-orchestrator-trigger
```

- Create `.env` file an fill variables:

```
TEST_STORAGE_API_TOKEN=
TEST_STORAGE_API_URL=

TEST_COMPONENT_ID=
TEST_COMPONENT_CONFIG_ID=
```

`TEST_COMPONENT_ID` and `TEST_COMPONENT_CONFIG_ID` variables are because Orchestration requires at least one configured task in order to run.

The easiest way is create new empty transformation bucket. Then use _"transformation"_ for `TEST_COMPONENT_ID` and bucket id for `TEST_COMPONENT_CONFIG_ID`.


- Build Docker image

```
docker-compose build
```

- Run the test suite using this command

    **Tests will delete all configured orchestrations in your KBC project!**

```
docker-compose run --rm dev composer ci
```
 
# Integration

For information about deployment and integration with KBC, please refer to the [deployment section of developers documentation](https://developers.keboola.com/extend/component/deployment/) 
