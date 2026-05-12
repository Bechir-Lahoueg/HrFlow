#!/bin/bash

# Branch to restore commits into
BRANCH="ameen"

# Commits recovered from reflog
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
            echo "Cherry-picking $COMMIT..."
            git cherry-pick "$COMMIT"

            if [ $? -ne 0 ]; then
                echo ""
                echo "Conflict detected!"
                echo "Resolve conflicts manually then run:"
                echo "    git cherry-pick --continue"
                echo ""
                echo "Or abort with:"
                echo "    git cherry-pick --abort"
                exit 1
            fi
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
