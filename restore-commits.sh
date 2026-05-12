#!/bin/bash

BRANCH="ameen"

COMMITS=(
    "52193cf"
    "4fd4b4b"
    "5e29205"
    "caee306"
    "315b41c"
)

echo "Checking out branch: $BRANCH"
git checkout "$BRANCH" || exit 1

echo ""
echo "Starting recovery process..."
echo ""

for COMMIT in "${COMMITS[@]}"
do
    echo "=================================================="
    echo "Reviewing commit: $COMMIT"
    echo "=================================================="

    git show --stat --oneline "$COMMIT"

    echo ""
    read -p "Cherry-pick this commit? (y/n/q): " ANSWER

    case $ANSWER in
        y|Y)

            # Detect local modifications
            if ! git diff --quiet || ! git diff --cached --quiet; then
                echo ""
                echo "Uncommitted changes detected."
                echo "Creating temporary stash..."

                STASH_NAME="auto-stash-before-$COMMIT-$(date +%s)"

                git stash push -u -m "$STASH_NAME"

                if [ $? -ne 0 ]; then
                    echo "Failed to create stash."
                    exit 1
                fi

                STASH_CREATED=true
            else
                STASH_CREATED=false
            fi

            echo ""
            echo "Cherry-picking $COMMIT..."
            git cherry-pick "$COMMIT"

            if [ $? -ne 0 ]; then
                echo ""
                echo "Conflict detected during cherry-pick!"
                echo ""
                echo "Resolve conflicts manually, then run:"
                echo "    git cherry-pick --continue"
                echo ""
                echo "Or abort with:"
                echo "    git cherry-pick --abort"
                echo ""
                echo "Your stash is still محفوظ (preserved)."

                exit 1
            fi

            # Restore stash if one was created
            if [ "$STASH_CREATED" = true ]; then
                echo ""
                echo "Restoring stashed changes..."

                git stash pop

                if [ $? -ne 0 ]; then
                    echo ""
                    echo "Conflict while restoring stash."
                    echo "Resolve manually."
                    exit 1
                fi
            fi

            echo ""
            echo "Commit restored successfully."
            ;;

        n|N)
            echo "Skipping $COMMIT"
            ;;

        q|Q)
            echo "Exiting..."
            exit 0
            ;;

        *)
            echo "Invalid input. Skipping..."
            ;;
    esac

    echo ""
done

echo "=================================================="
echo "Recovery complete."
echo "=================================================="

echo ""
read -p "Push restored commits to remote? (y/n): " PUSHANSWER

if [[ "$PUSHANSWER" =~ ^[Yy]$ ]]; then
    git push origin "$BRANCH"
fi
