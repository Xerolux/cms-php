import { gql } from '@apollo/client';

// Post Subscriptions
export const POST_CREATED = gql`
  subscription OnPostCreated {
    postCreated {
      id
      title
      slug
      status
      created_at
      author {
        id
        name
        avatar_url
      }
    }
  }
`;

export const POST_UPDATED = gql`
  subscription OnPostUpdated($postId: ID) {
    postUpdated(postId: $postId) {
      id
      title
      slug
      status
      updated_at
      author {
        id
        name
      }
    }
  }
`;

export const POST_DELETED = gql`
  subscription OnPostDeleted {
    postDeleted {
      id
      title
    }
  }
`;

// Comment Subscriptions
export const COMMENT_ADDED = gql`
  subscription OnCommentAdded($postId: ID!) {
    commentAdded(postId: $postId) {
      id
      content
      status
      created_at
      author {
        id
        name
        avatar_url
      }
      post {
        id
        title
      }
    }
  }
`;

// Notification Subscriptions
export const NOTIFICATION_ADDED = gql`
  subscription OnNotificationAdded {
    notificationAdded {
      id
      type
      title
      message
      data
      read_at
      created_at
    }
  }
`;
